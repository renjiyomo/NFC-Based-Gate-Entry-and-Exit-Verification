<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\AuditLog;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\Credentials\CredentialToken;
use App\Services\Credentials\CredentialVerifier;
use App\Services\GateOperations\GateOperations;
use App\Services\PersonPhotos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\ManagementTestCase;

class GateOperationsTest extends ManagementTestCase
{
    private User $guard;

    private Gate $station;

    private GuardGateAssignment $assignment;

    private Person $person;

    private array $receipt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->guard = $this->user(['role' => 'guard']);
        $this->station = Gate::create(['code' => 'LAB-'.Str::random(8), 'name' => 'Synthetic test station', 'status' => 'active']);
        $this->assignment = $this->assign($this->guard);
        $this->person = $this->makePerson();
        $this->person->authorizations()->create(['status' => 'active', 'valid_from' => now()->subMinute(), 'granted_by' => $this->admin->id]);
        $this->receipt = $this->credential();
    }

    private function assign(User $guard): GuardGateAssignment
    {
        return GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $this->station->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
    }

    private function credential(string $source = 'physical', array $extra = []): array
    {
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($this->admin, ['person_id' => $this->person->id, 'record_version' => $this->person->fresh()->record_version, 'label' => 'GATE-'.Str::random(12), 'mode' => 'new', 'request_id' => (string) Str::uuid(), ...$extra], 'issuer', $source);
        $target = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($this->admin, $target, 'issuer', $receipt['payload'], $receipt['completion_nonce'], $source);
        $service->activate($this->admin, $target, 'issuer', $receipt['payload'], $receipt['completion_nonce'], $source);

        return $receipt;
    }

    private function scan(array $extra = [], ?User $guard = null, string $session = 'guard-session', string $source = 'physical'): array
    {
        return app(GateOperations::class)->scan($guard ?? $this->guard, $this->station, ['payload' => $this->receipt['payload'], 'request_id' => (string) Str::uuid(), 'direction' => 'entry', ...$extra], $session, $source);
    }

    private function attempt(array $result): ScanAttempt
    {
        return ScanAttempt::where('public_id', $result['id'])->sole();
    }

    private function review(array $result, array $extra = [], ?User $guard = null): array
    {
        return app(GateOperations::class)->review($guard ?? $this->guard, $this->station, $this->attempt($result), ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document', ...$extra], 'guard-session', $result['source']);
    }

    private function httpDenied(callable $action, int $status = 403): void
    {
        try {
            $action();
            $this->fail('Expected HTTP rejection.');
        } catch (ModelNotFoundException) {
            $this->assertSame(404, $status);
        } catch (HttpException $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_scan_allows_review_but_does_not_record_passage_or_expose_reference(): void
    {
        $result = $this->scan();
        $this->assertSame('allowed', $result['verification']['outcome']);
        $this->assertSame('pending', $result['review']['status']);
        $this->assertSame($this->person->full_name, $result['identity']['name']);
        $this->assertSame(30.0, now()->diffInSeconds($this->attempt($result)->review_expires_at));
        $this->assertSame(0, AccessEvent::count());
        $this->assertNull($result['passage']);
        foreach ([$this->receipt['payload'], $this->receipt['completion_nonce'], 'payload_fingerprint', 'session_digest'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($result));
            $this->assertStringNotContainsString($secret, $this->attempt($result)->toJson());
            $this->assertStringNotContainsString($secret, AuditLog::get()->toJson());
        }
    }

    public function test_explicit_confirmation_creates_one_immutable_event_and_retry_is_idempotent(): void
    {
        $result = $this->scan();
        $first = $this->review($result);
        $again = $this->review($result);
        $this->assertSame('confirmed', $first['review']['status']);
        $this->assertSame($first['passage']['id'], $again['passage']['id']);
        $this->assertNull($first['identity']);
        $this->assertSame(1, AccessEvent::count());
        $this->assertSame(1, AuditLog::where('action', 'passage.recorded')->count());
        $this->assertSame('entry', AccessEvent::sole()->direction);
    }

    public function test_retries_preserve_attempt_and_deadline_and_changed_payload_gate_or_direction_are_rejected(): void
    {
        $id = (string) Str::uuid();
        $first = $this->scan(['request_id' => $id]);
        $this->travel(5)->seconds();
        $again = $this->scan(['request_id' => $id]);
        $this->assertSame($first['id'], $again['id']);
        $this->assertSame($first['review']['expires_at'], $again['review']['expires_at']);
        foreach ([['direction' => 'exit'], ['payload' => 'different']] as $change) {
            try {
                $this->scan(['request_id' => $id, ...$change]);
                $this->fail('Expected request conflict.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('request_id', $error->errors());
            }
        }
        $this->assertSame(1, ScanAttempt::count());
    }

    public function test_unknown_malformed_revoked_and_inactive_references_do_not_reveal_identity_or_create_passage(): void
    {
        foreach (['bad', app(CredentialToken::class)->generate()] as $payload) {
            $result = $this->scan(['payload' => $payload]);
            $this->assertSame('denied', $result['verification']['outcome']);
            $this->assertSame('not_applicable', $result['review']['status']);
            $this->assertNull($result['identity']);
        }
        $this->person->update(['status' => 'inactive']);
        $this->assertSame('person_inactive', $this->scan()['verification']['reason']);
        $this->person->update(['status' => 'active']);
        app(CredentialLifecycle::class)->terminate($this->admin, NfcCredential::where('public_id', $this->receipt['credential']['id'])->sole(), 'revoke', 'lost');
        $this->assertSame('revoked', $this->scan()['verification']['reason']);
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_confirmation_rechecks_credential_person_grant_revision_and_gate_assignment(): void
    {
        $result = $this->scan();
        $this->person->update(['status' => 'inactive']);
        $closed = $this->review($result);
        $this->assertSame('cancelled', $closed['review']['status']);
        $this->assertFalse($closed['current_check']['eligible']);
        $this->person->update(['status' => 'active']);
        $fresh = $this->scan();
        $this->person->fresh()->forceFill(['record_version' => $this->person->fresh()->record_version + 1])->save();
        $this->assertSame('person_record_changed', $this->review($fresh)['review']['reason']);
        $fresh = $this->scan();
        $this->person->authorizations()->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->assertSame('recheck_access_not_authorized', $this->review($fresh)['review']['reason']);
        $this->assignment->update(['revoked_at' => now()]);
        $this->httpDenied(fn () => $this->review($result));
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_revocation_and_replacement_between_scan_and_confirmation_cancel_stale_review(): void
    {
        $result = $this->scan();
        $old = $this->receipt;
        $this->receipt = $this->credential(extra: ['mode' => 'replace', 'replaces_id' => $old['credential']['id']]);
        $this->assertSame('recheck_revoked', $this->review($result)['review']['reason']);
        $this->assertSame('confirmed', $this->review($this->scan())['review']['status']);
        $this->assertSame(1, AccessEvent::count());
    }

    public function test_expiry_at_exact_boundary_prevents_confirmation_and_retries_do_not_extend_it(): void
    {
        $result = $this->scan();
        $this->travelTo($this->attempt($result)->review_expires_at);
        $expired = $this->review($result);
        $this->assertSame('expired', $expired['review']['status']);
        $this->assertNull($expired['identity']);
        $this->assertNull($this->scan(['request_id' => $result['request_id']])['passage']);
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_cancellation_and_identity_mismatch_are_terminal_and_never_become_passage(): void
    {
        foreach (['cancel', 'identity_mismatch'] as $action) {
            $result = $this->scan();
            $closed = $this->review($result, ['action' => $action]);
            $this->assertSame($action === 'cancel' ? 'cancelled' : 'identity_mismatch', $closed['review']['status']);
            $this->assertNull($this->review($result)['passage']);
        }
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_duplicate_window_suppresses_different_requests_and_guards_but_allows_boundary(): void
    {
        $first = $this->review($this->scan());
        $other = $this->user(['role' => 'guard']);
        $this->assign($other);
        $next = $this->scan(guard: $other);
        $duplicate = $this->review($next, guard: $other);
        $this->assertSame('duplicate_passage', $duplicate['review']['reason']);
        $this->assertSame($first['passage']['id'], $duplicate['duplicate_of']['id']);
        $this->assertNull($duplicate['passage']);
        $this->assertSame(1, AccessEvent::count());
        $this->travel(10)->seconds();
        $later = $this->review($this->scan());
        $this->assertSame('confirmed', $later['review']['status']);
        $this->assertSame('repeated_entry', $later['passage']['sequence_notice']);
        $this->assertSame(2, AccessEvent::count());
    }

    public function test_exit_is_explicit_and_unknown_prior_state_is_a_notice_not_automatic_denial(): void
    {
        $exit = $this->review($this->scan(['direction' => 'exit']));
        $this->assertSame('exit', $exit['passage']['direction']);
        $this->assertSame('exit_without_recorded_entry', $exit['passage']['sequence_notice']);
        $entry = $this->review($this->scan(['direction' => 'entry']));
        $this->assertSame('entry', $entry['passage']['direction']);
        $this->assertSame(2, AccessEvent::count());
    }

    public function test_duplicate_window_retains_microsecond_precision_at_the_boundary(): void
    {
        $this->travelTo(now()->setMicrosecond(750000));
        $first = $this->review($this->scan());
        $at = CarbonImmutable::parse($first['passage']['confirmed_at']);
        $this->travelTo($at->addSeconds(10)->subMicrosecond());
        $this->assertSame('duplicate_passage', $this->review($this->scan())['review']['reason']);
        $this->travelTo($at->addSeconds(10));
        $this->assertSame('confirmed', $this->review($this->scan())['review']['status']);
        $this->assertSame(2, AccessEvent::count());
    }

    public function test_guard_photo_is_available_only_during_the_same_live_authorized_review(): void
    {
        Storage::fake('local');
        $path = app(PersonPhotos::class)->store(UploadedFile::fake()->image('synthetic.png', 120, 120));
        $this->person->update(['photo_path' => $path]);
        $this->guard->forceFill(['gate_id' => $this->station->id])->save();
        $this->signIn($this->guard);
        $base = '/guard/gates/'.$this->station->id;
        $result = $this->postJson($base.'/automatic-scan', ['payload' => $this->receipt['payload'], 'request_id' => (string) Str::uuid()])->assertOk()->json();
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
        $photo = $this->get($result['identity']['photo_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', $photo->headers->get('Cache-Control'));
        $this->travel(31)->minutes();
        $this->get($result['identity']['photo_url'])->assertNotFound();
    }

    public function test_actor_gate_source_session_and_request_ownership_are_checked(): void
    {
        $result = $this->scan();
        $service = app(GateOperations::class);
        $other = $this->user(['role' => 'guard']);
        $this->assign($other);
        $this->httpDenied(fn () => $this->scan(guard: $this->admin));
        $this->httpDenied(fn () => $service->recover($other, $this->station, $result['request_id'], 'guard-session', 'physical'), 404);
        $this->httpDenied(fn () => $service->recover($this->guard, $this->station, $result['request_id'], 'other-session', 'physical'), 404);
        $this->guard->update(['status' => 'inactive']);
        $this->httpDenied(fn () => $this->review($result));
        $this->guard->update(['status' => 'active']);
        $this->httpDenied(fn () => $this->review($result), 404);
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_photo_requires_live_owned_review_and_cannot_browse_person_directory(): void
    {
        $result = $this->scan();
        $this->guard->forceFill(['gate_id' => $this->station->id])->save();
        $this->signIn($this->guard);
        $base = '/guard/gates/'.$this->station->id;
        $this->get($base.'/attempts/'.$result['id'].'/photo')->assertNotFound();
        $this->get('/admin/students/'.$this->person->id.'/photo')->assertForbidden();
        $this->get($base.'/attempts/'.$result['id'].'/photo')->assertNotFound();
    }

    public function test_identity_check_is_required_and_photo_method_requires_available_photo(): void
    {
        $result = $this->scan();
        foreach ([['identity_checked' => false], ['identity_method' => 'photo']] as $invalid) {
            try {
                $this->review($result, $invalid);
                $this->fail('Expected identity check validation.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_failed_essential_audit_rolls_back_passage_and_review_transition(): void
    {
        $result = $this->scan();
        $audit = \Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('synthetic audit failure'));
        $service = new GateOperations(app(CredentialVerifier::class), $audit);
        try {
            $service->review($this->guard, $this->station, $this->attempt($result), ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'], 'guard-session', 'physical');
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('synthetic audit failure', $error->getMessage());
        }
        $this->assertSame('pending', $this->attempt($result)->review_status);
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_runtime_sql_cannot_rewrite_verification_facts_or_update_delete_passage_events(): void
    {
        $result = $this->scan();
        $this->review($result);
        foreach ([['UPDATE scan_attempts SET direction = ? WHERE id = ?', ['exit', $this->attempt($result)->id]], ['DELETE FROM scan_attempts WHERE id = ?', [$this->attempt($result)->id]], ['UPDATE access_events SET direction = ? WHERE id = ?', ['exit', AccessEvent::sole()->id]], ['DELETE FROM access_events WHERE id = ?', [AccessEvent::sole()->id]]] as [$sql, $bindings]) {
            try {
                DB::statement($sql, $bindings);
                $this->fail('Expected narrow runtime privilege denial.');
            } catch (QueryException $error) {
                $this->assertContains($error->errorInfo[1], [1142, 1143]);
            }
        }
    }

    public function test_model_history_is_immutable_and_database_prevents_mismatched_event_owner(): void
    {
        $result = $this->scan();
        $this->review($result);
        foreach ([fn () => $this->attempt($result)->update(['direction' => 'exit']), fn () => AccessEvent::sole()->update(['direction' => 'exit']), fn () => $this->attempt($result)->delete()] as $mutate) {
            try {
                $mutate();
                $this->fail('Expected immutable history.');
            } catch (\LogicException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $next = $this->scan();
        $record = AccessEvent::sole()->getAttributes();
        unset($record['id']);
        $record['public_id'] = (string) Str::uuid();
        $record['scan_attempt_id'] = $this->attempt($next)->id;
        $record['direction'] = 'exit';
        $this->expectException(QueryException::class);
        DB::table('access_events')->insert($record);
    }

    public function test_expiry_cleanup_is_idempotent_and_does_not_record_passage(): void
    {
        $result = $this->scan();
        $this->travel(30)->seconds();
        $this->artisan('nfcheck:expire-reviews')->assertSuccessful();
        $this->artisan('nfcheck:expire-reviews')->assertSuccessful();
        $this->assertSame('expired', $this->attempt($result)->review_status);
        $this->assertSame(1, AuditLog::where('action', 'verification.review_expired')->count());
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_database_rejects_null_allowed_deadlines_and_wrong_reviewers(): void
    {
        $result = $this->scan();
        $record = $this->attempt($result)->getAttributes();
        unset($record['id']);
        $record['public_id'] = (string) Str::uuid();
        $record['request_id'] = (string) Str::uuid();
        $record['review_expires_at'] = null;
        try {
            DB::table('scan_attempts')->insert($record);
            $this->fail('Expected explicit non-null review deadline.');
        } catch (QueryException $error) {
            $this->assertSame(3819, $error->errorInfo[1]);
        }
        try {
            DB::table('scan_attempts')->where('id', $this->attempt($result)->id)->update(['review_status' => 'cancelled', 'reviewed_at' => now(), 'reviewed_by' => $this->admin->id]);
            $this->fail('Expected same-guard reviewer constraint.');
        } catch (QueryException $error) {
            $this->assertSame(3819, $error->errorInfo[1]);
        }
        $this->assertSame('pending', $this->attempt($result)->fresh()->review_status);
    }

    public function test_simulation_uses_shared_decision_service_and_cannot_create_physical_passage(): void
    {
        app(CredentialLifecycle::class)->terminate($this->admin, NfcCredential::where('public_id', $this->receipt['credential']['id'])->sole(), 'revoke', 'lost');
        $this->receipt = $this->credential('simulated');
        $this->assertSame('simulated_not_permitted', $this->scan()['verification']['reason']);
        $this->httpDenied(fn () => $this->scan(source: 'simulated'), 404);
        config(['nfcheck.nfc_simulator' => true]);
        $result = $this->scan(source: 'simulated');
        $this->assertSame('allowed', $result['verification']['outcome']);
        $this->assertSame('simulated', $this->review($result)['passage']['source']);
    }

    public function test_http_routes_require_guard_assignment_json_and_server_fixed_source(): void
    {
        $base = '/guard/gates/'.$this->station->id;
        $this->postJson($base.'/automatic-scan', [])->assertForbidden();
        $this->guard->forceFill(['gate_id' => $this->station->id])->save();
        $this->signIn($this->guard);
        $this->post($base.'/automatic-scan', [])->assertStatus(406);
        $data = ['payload' => $this->receipt['payload'], 'request_id' => (string) Str::uuid(), 'source' => 'simulated'];
        $this->postJson($base.'/automatic-scan', $data)->assertUnprocessable()->assertJsonValidationErrors('source');
        unset($data['source']);
        $response = $this->postJson($base.'/automatic-scan', $data)->assertOk()->assertJsonPath('source', 'physical');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
        $this->getJson($base.'/requests/'.$data['request_id'])->assertOk()->assertJsonPath('id', $response->json('id'));
        $this->postJson($base.'/attempts/'.$response->json('id').'/review', ['action' => 'confirm', 'identity_checked' => false, 'identity_method' => 'campus_document'])->assertUnprocessable();
        $this->postJson($base.'/attempts/'.$response->json('id').'/review', ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'])->assertStatus(410);
    }

    public function test_guard_activity_is_limited_to_current_assignments_window_and_does_not_leak_denied_identity(): void
    {
        $this->scan();
        $this->person->update(['status' => 'inactive']);
        $this->scan();
        $this->guard->forceFill(['gate_id' => $this->station->id])->save();
        $this->signIn($this->guard)->getJson('/guard/activity/feed')->assertOk()->assertJsonCount(2, 'records')->assertJsonPath('records.0.person', null)->assertJsonPath('minutes', 30);
        $this->station->update(['status' => 'inactive']);
        $this->getJson('/guard/activity/feed')->assertOk()->assertJsonCount(0, 'records');
        $this->getJson('/admin/history/feed')->assertForbidden();
        $this->signIn($this->admin)->getJson('/admin/history/feed')->assertOk()->assertJsonCount(2, 'records');
    }
}
