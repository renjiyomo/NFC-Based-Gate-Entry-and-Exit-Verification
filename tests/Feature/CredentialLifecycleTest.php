<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Services\AuditLogger;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\Credentials\CredentialToken;
use App\Services\Credentials\CredentialVerifier;
use App\Services\ManagementGuard;
use App\Services\PersonManagement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\ManagementTestCase;

class CredentialLifecycleTest extends ManagementTestCase
{
    private function issue(Person $person, array $changes = [], string $source = 'physical'): array
    {
        return app(CredentialLifecycle::class)->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->fresh()->record_version, 'label' => 'LAB-'.Str::random(12), 'mode' => 'new', 'request_id' => (string) Str::uuid(), ...$changes], 'lab-session', $source);
    }

    private function credential(array $receipt): NfcCredential
    {
        return NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
    }

    private function activate(array $receipt): NfcCredential
    {
        $service = app(CredentialLifecycle::class);
        $credential = $this->credential($receipt);
        $service->readBack($this->admin, $credential, 'lab-session', $receipt['payload'], $receipt['completion_nonce'], $credential->source);

        return $service->activate($this->admin, $credential, 'lab-session', $receipt['payload'], $receipt['completion_nonce'], $credential->source);
    }

    private function authorizedPerson(): Person
    {
        $person = $this->makePerson();
        $person->authorizations()->create(['status' => 'active', 'valid_from' => now()->subMinute(), 'granted_by' => $this->admin->id]);

        return $person;
    }

    private function rejects(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Expected validation rejection.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    public function test_references_are_random_canonical_and_only_digests_are_persisted(): void
    {
        $tokens = app(CredentialToken::class);
        $values = [];
        for ($i = 0; $i < 100; $i++) {
            $value = $tokens->generate();
            $this->assertSame(32, strlen($tokens->digest($value)));
            $values[] = $value;
        }
        $this->assertCount(100, array_unique($values));
        $receipt = $this->issue($this->makePerson());
        $credential = $this->credential($receipt);
        $this->assertSame($tokens->digest($receipt['payload']), $credential->token_digest);
        $serialized = $credential->toJson();
        foreach (['token_digest', 'completion_digest', 'issuer_session_digest', $receipt['payload'], $receipt['completion_nonce']] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $this->assertStringNotContainsString($receipt['payload'], AuditLog::get()->toJson());
        $this->assertNull($credential->assignment->person->active_credential_assignment_id);
    }

    public function test_malformed_noncanonical_unknown_and_pending_references_are_denied(): void
    {
        $verifier = app(CredentialVerifier::class);
        foreach (['', 'https://example.invalid', 'nfcheck:v2:'.str_repeat('A', 43), 'nfcheck:v1:'.str_repeat('A', 42).'B', 'nfcheck:v1:'.str_repeat('A', 43).'=', str_repeat('A', 500)] as $payload) {
            $this->assertSame('malformed', $verifier->inspect($payload)['reason']);
        }
        $this->assertSame('unknown', $verifier->inspect(app(CredentialToken::class)->generate())['reason']);
        $receipt = $this->issue($this->makePerson());
        $this->assertSame('pending', $verifier->inspect($receipt['payload'])['reason']);
    }

    public function test_activation_requires_matching_readback_and_is_idempotent(): void
    {
        $receipt = $this->issue($this->authorizedPerson());
        $credential = $this->credential($receipt);
        $service = app(CredentialLifecycle::class);
        $this->rejects(fn () => $service->activate($this->admin, $credential, 'lab-session', $receipt['payload'], $receipt['completion_nonce'], 'physical'), 'issuance');
        $this->activate($receipt);
        $again = $service->activate($this->admin, $credential, 'lab-session', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $this->assertSame('active', $again->status);
        $this->assertSame(1, AuditLog::where('action', 'credential.activated')->count());
        $verifier = app(CredentialVerifier::class);
        $this->assertTrue($verifier->inspect($receipt['payload'])['eligible']);
        $this->assertTrue($verifier->inspect($receipt['payload'])['eligible']);
        $this->assertSame(1, DB::table('credential_assignments')->count());
    }

    public function test_completion_is_bound_to_actor_session_nonce_payload_and_source(): void
    {
        $receipt = $this->issue($this->makePerson());
        $credential = $this->credential($receipt);
        $service = app(CredentialLifecycle::class);
        foreach ([[$this->user(), 'lab-session', $receipt['payload'], $receipt['completion_nonce'], 'physical'], [$this->admin, 'different-session', $receipt['payload'], $receipt['completion_nonce'], 'physical'], [$this->admin, 'lab-session', $receipt['payload'], str_repeat('0', 64), 'physical'], [$this->admin, 'lab-session', $receipt['payload'], $receipt['completion_nonce'], 'simulated']] as $arguments) {
            $this->rejects(fn () => $service->readBack($arguments[0], $credential, ...array_slice($arguments, 1)), 'issuance');
        }
        $this->rejects(fn () => $service->readBack($this->admin, $credential, 'lab-session', app(CredentialToken::class)->generate(), $receipt['completion_nonce'], 'physical'), 'payload');
        $this->assertNull($credential->fresh()->read_back_at);
        $this->admin->update(['password' => 'another long synthetic password 83']);
        $this->rejects(fn () => $service->readBack($this->admin, $credential, 'lab-session', $receipt['payload'], $receipt['completion_nonce'], 'physical'), 'issuance');
    }

    public function test_another_persons_valid_tag_cannot_complete_a_pending_http_issuance(): void
    {
        $this->signIn($this->admin);
        $issue = function (Person $person): array {
            $response = $this->postJson('/admin/credentials/issue', [
                'person_id' => $person->id,
                'record_version' => $person->fresh()->record_version,
                'mode' => 'new',
                'request_id' => (string) Str::uuid(),
            ])->assertCreated();
            $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());

            return $response->json();
        };
        $student = $this->makePerson();
        $employee = $this->makePerson('employees');
        $first = $issue($student);
        $firstBase = '/admin/credentials/'.$first['credential']['id'];
        $firstCompletion = ['payload' => $first['payload'], 'completion_nonce' => $first['completion_nonce']];
        $this->postJson($firstBase.'/read-back', $firstCompletion)->assertOk();
        $this->postJson($firstBase.'/activate', $firstCompletion)->assertOk();
        $studentAssignment = $student->fresh()->active_credential_assignment_id;

        $second = $issue($employee);
        $secondBase = '/admin/credentials/'.$second['credential']['id'];
        $this->assertNotSame($first['payload'], $second['payload']);
        $this->postJson($secondBase.'/read-back', ['payload' => $first['payload'], 'completion_nonce' => $second['completion_nonce']])
            ->assertUnprocessable()->assertJsonValidationErrors('payload');
        $this->assertNull($this->credential($second)->read_back_at);
        $this->assertNull($employee->fresh()->active_credential_assignment_id);
        $this->assertSame($studentAssignment, $student->fresh()->active_credential_assignment_id);

        $secondCompletion = ['payload' => $second['payload'], 'completion_nonce' => $second['completion_nonce']];
        $this->postJson($secondBase.'/activate', $secondCompletion)->assertUnprocessable()->assertJsonValidationErrors('issuance');
        $this->assertSame('pending', $this->credential($second)->status);
        $this->postJson($secondBase.'/read-back', $secondCompletion)->assertOk();
        $this->postJson($secondBase.'/activate', $secondCompletion)->assertOk()->assertJsonPath('credential.selected', true);
        $this->assertSame('active', $this->credential($first)->status);
        $this->assertSame($studentAssignment, $student->fresh()->active_credential_assignment_id);
        $this->assertSame(0, DB::table('access_events')->count());
    }

    public function test_duplicate_pending_stale_records_and_request_replays_cannot_issue_again(): void
    {
        $person = $this->makePerson();
        $id = (string) Str::uuid();
        $receipt = $this->issue($person, ['request_id' => $id]);
        $this->rejects(fn () => $this->issue($person), 'person_id');
        $this->rejects(fn () => $this->issue($person, ['request_id' => $id]), 'request_id');
        $this->rejects(fn () => $this->issue($person, ['record_version' => 1]), 'record_version');
        $this->assertSame(1, NfcCredential::count());
        $this->assertSame('pending', $this->credential($receipt)->status);
    }

    public function test_pending_expiry_is_enforced_at_boundary_before_cleanup(): void
    {
        $receipt = $this->issue($this->makePerson());
        $credential = $this->credential($receipt);
        $this->travelTo($credential->pending_expires_at);
        $this->assertSame('expired', app(CredentialVerifier::class)->inspect($receipt['payload'])['reason']);
        $this->rejects(fn () => $this->activate($receipt), 'issuance');
        $this->artisan('nfcheck:expire-credentials')->assertSuccessful();
        $this->artisan('nfcheck:expire-credentials')->assertSuccessful();
        $this->assertSame('expired', $credential->fresh()->status);
        $this->assertNotNull($credential->assignment->ended_at);
        $this->assertSame(1, AuditLog::where('action', 'credential.expired')->count());
    }

    public function test_expired_active_credential_cleanup_clears_selection_and_retains_history(): void
    {
        $person = $this->authorizedPerson();
        $receipt = $this->issue($person, ['valid_until' => now('Asia/Manila')->addHour()->format('Y-m-d\TH:i')]);
        $credential = $this->activate($receipt);
        $this->travelTo($credential->valid_until);
        $this->assertSame('expired', app(CredentialVerifier::class)->inspect($receipt['payload'])['reason']);
        $this->getJson('/admin/credentials/people?q='.$person->campus_number)->assertOk()->assertJsonPath('data.0.current', null);
        $this->artisan('nfcheck:expire-credentials')->assertSuccessful();
        $this->assertNull($person->fresh()->active_credential_assignment_id);
        $this->assertSame('expired', $credential->fresh()->status);
        $this->assertNotNull($credential->assignment->ended_at);
        $this->assertSame(1, DB::table('credential_assignments')->count());
    }

    public function test_cancelled_or_timed_out_issuance_allows_fresh_reference_without_reusing_old_secret(): void
    {
        $person = $this->makePerson();
        $old = $this->issue($person);
        app(CredentialLifecycle::class)->terminate($this->admin, $this->credential($old), 'cancel', 'issuance_cancelled');
        $new = $this->issue($person);
        $this->assertNotSame($old['payload'], $new['payload']);
        $this->travelTo($this->credential($new)->pending_expires_at);
        $next = $this->issue($person);
        $this->assertSame('expired', $this->credential($new)->status);
        $this->assertNotSame($new['payload'], $next['payload']);
        $this->assertSame(3, DB::table('credential_assignments')->count());
    }

    public function test_inactive_people_cannot_receive_credentials_and_manual_labels_are_rejected(): void
    {
        $inactive = $this->makePerson(overrides: ['status' => 'inactive']);
        $this->rejects(fn () => $this->issue($inactive), 'person_id');
        $receipt = $this->issue($this->makePerson());
        $person = $this->makePerson('employees');
        $this->postJson('/admin/credentials/issue', ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => $receipt['credential']['label'], 'mode' => 'new', 'request_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('label');
        $this->assertSame(1, NfcCredential::count());
    }

    public function test_person_changes_invalidate_pending_completion(): void
    {
        $person = $this->makePerson();
        $receipt = $this->issue($person);
        $person->fresh()->forceFill(['record_version' => 3])->save();
        $this->rejects(fn () => $this->activate($receipt), 'record_version');
    }

    public function test_replacement_switches_selection_and_revokes_old_credential_atomically(): void
    {
        $person = $this->authorizedPerson();
        $old = $this->issue($person);
        $this->activate($old);
        $new = $this->issue($person, ['mode' => 'replace', 'replaces_id' => $old['credential']['id']]);
        $this->assertTrue(app(CredentialVerifier::class)->inspect($old['payload'])['eligible']);
        $this->activate($new);
        $this->assertSame('revoked', app(CredentialVerifier::class)->inspect($old['payload'])['reason']);
        $this->assertTrue(app(CredentialVerifier::class)->inspect($new['payload'])['eligible']);
        $this->assertSame('replaced', $this->credential($old)->close_reason);
        $this->assertNotNull($this->credential($old)->assignment->ended_at);
        $this->assertSame($this->credential($new)->assignment->id, $person->fresh()->active_credential_assignment_id);
    }

    public function test_failed_replacement_audit_rolls_back_old_revocation_and_new_activation(): void
    {
        $person = $this->authorizedPerson();
        $old = $this->issue($person);
        $this->activate($old);
        $new = $this->issue($person, ['mode' => 'replace', 'replaces_id' => $old['credential']['id']]);
        $target = $this->credential($new);
        app(CredentialLifecycle::class)->readBack($this->admin, $target, 'lab-session', $new['payload'], $new['completion_nonce'], 'physical');
        $audit = \Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('synthetic audit failure'));
        $service = new CredentialLifecycle(app(CredentialToken::class), app(ManagementGuard::class), $audit);
        try {
            $service->activate($this->admin, $target, 'lab-session', $new['payload'], $new['completion_nonce'], 'physical');
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('synthetic audit failure', $error->getMessage());
        }
        $this->assertSame('active', $this->credential($old)->status);
        $this->assertNull($this->credential($old)->assignment->ended_at);
        $this->assertSame('pending', $target->fresh()->status);
        $this->assertTrue(app(CredentialVerifier::class)->inspect($old['payload'])['eligible']);
    }

    public function test_lost_revocation_is_committed_before_replacement_and_cannot_be_undone(): void
    {
        $person = $this->authorizedPerson();
        $old = $this->issue($person);
        $this->activate($old);
        $this->rejects(fn () => $this->issue($person, ['mode' => 'lost', 'replaces_id' => $old['credential']['id']]), 'mode');
        app(CredentialLifecycle::class)->terminate($this->admin, $this->credential($old), 'revoke', 'lost');
        $this->assertNull($person->fresh()->active_credential_assignment_id);
        $this->rejects(fn () => $this->issue($person, ['mode' => 'lost', 'replaces_id' => $old['credential']['id'], 'valid_until' => now('Asia/Manila')->subMinute()->format('Y-m-d\TH:i')]), 'valid_until');
        $this->assertSame('revoked', $this->credential($old)->status);
        $new = $this->issue($person, ['mode' => 'lost', 'replaces_id' => $old['credential']['id']]);
        $this->activate($new);
        $this->assertTrue(app(CredentialVerifier::class)->inspect($new['payload'])['eligible']);
        $this->rejects(fn () => $this->activate($old), 'issuance');
    }

    public function test_revocation_cancellation_and_ownership_are_permanent(): void
    {
        $receipt = $this->issue($this->makePerson());
        $credential = $this->credential($receipt);
        app(CredentialLifecycle::class)->terminate($this->admin, $credential, 'cancel', 'issuance_cancelled');
        $this->rejects(fn () => $this->activate($receipt), 'issuance');
        foreach ([fn () => $credential->fresh()->update(['status' => 'active']), fn () => $credential->fresh()->update(['source' => 'simulated']), fn () => $credential->assignment->update(['person_id' => $this->makePerson()->id]), fn () => $credential->delete()] as $change) {
            try {
                $change();
                $this->fail('Expected immutable history.');
            } catch (\LogicException|\DomainException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $this->assertSame('cancelled', app(CredentialVerifier::class)->inspect($receipt['payload'])['reason']);
    }

    public function test_verification_requires_active_person_current_selection_grant_and_validity(): void
    {
        $person = $this->makePerson();
        $receipt = $this->issue($person, ['valid_until' => now('Asia/Manila')->addHour()->format('Y-m-d\TH:i')]);
        $credential = $this->activate($receipt);
        $verifier = app(CredentialVerifier::class);
        $this->assertSame('eligible_for_review', $verifier->inspect($receipt['payload'])['reason']);
        $grant = $person->authorizations()->create(['status' => 'active', 'valid_from' => now()->subMinute(), 'valid_until' => now()->addMinutes(20), 'granted_by' => $this->admin->id]);
        $this->assertTrue($verifier->inspect($receipt['payload'])['eligible']);
        $person->fresh()->update(['status' => 'inactive']);
        $this->assertSame('person_inactive', $verifier->inspect($receipt['payload'])['reason']);
        $person->fresh()->forceFill(['status' => 'active', 'active_credential_assignment_id' => null])->save();
        $this->assertSame('not_selected', $verifier->inspect($receipt['payload'])['reason']);
        $person->fresh()->forceFill(['active_credential_assignment_id' => $credential->assignment->id])->save();
        $this->travelTo($grant->valid_until);
        $this->assertSame('eligible_for_review', $verifier->inspect($receipt['payload'])['reason']);
        $this->travelTo($credential->valid_until);
        $this->assertSame('expired', $verifier->inspect($receipt['payload'])['reason']);
    }

    public function test_archive_closes_active_and_pending_credentials_restore_does_not_reactivate(): void
    {
        $person = $this->authorizedPerson();
        $old = $this->issue($person);
        $this->activate($old);
        $new = $this->issue($person, ['mode' => 'replace', 'replaces_id' => $old['credential']['id']]);
        app(PersonManagement::class)->archive($this->admin, $person, $person->fresh()->record_version);
        $this->assertSame('revoked', $this->credential($old)->status);
        $this->assertSame('cancelled', $this->credential($new)->status);
        $archived = Person::withTrashed()->findOrFail($person->id);
        app(PersonManagement::class)->archive($this->admin, $archived, $archived->record_version, true);
        $this->assertNull($person->fresh()->active_credential_assignment_id);
        $this->assertSame('inactive', $person->fresh()->status->value);
        $this->assertSame('revoked', $this->credential($old)->status);
    }

    public function test_simulated_credential_cannot_pass_normal_verifier(): void
    {
        $receipt = $this->issue($this->authorizedPerson(), source: 'simulated');
        $this->activate($receipt);
        $this->assertSame('simulated_not_permitted', app(CredentialVerifier::class)->inspect($receipt['payload'])['reason']);
        $this->assertTrue(app(CredentialVerifier::class)->inspect($receipt['payload'], true)['eligible']);
        $this->postJson('/admin/credentials/check', ['payload' => $receipt['payload'], 'simulated' => true])->assertOk()->assertJsonPath('eligible', false)->assertJsonPath('reason', 'simulated_not_permitted')->assertJsonPath('passage_recorded', false);
    }

    public function test_owner_qualified_pointer_and_digest_uniqueness_are_enforced_in_mysql(): void
    {
        $receipt = $this->issue($this->makePerson());
        $credential = $this->credential($receipt);
        try {
            DB::table('persons')->where('id', $this->makePerson()->id)->update(['active_credential_assignment_id' => $credential->assignment->id]);
            $this->fail('Expected owner foreign key denial.');
        } catch (QueryException $error) {
            $this->assertSame(1452, $error->errorInfo[1]);
        }
        $second = $this->issue($this->makePerson());
        $this->expectException(QueryException::class);
        DB::table('nfc_credentials')->where('public_id', $second['credential']['id'])->update(['token_digest' => $credential->token_digest]);
    }

    public function test_http_receipt_is_no_store_and_registry_person_and_detail_props_never_contain_secrets(): void
    {
        $person = $this->makePerson();
        $data = ['person_id' => $person->id, 'record_version' => $person->record_version, 'mode' => 'new', 'request_id' => (string) Str::uuid(), 'source' => 'simulated'];
        $response = $this->postJson('/admin/credentials/issue', $data)->assertCreated()->assertJsonPath('credential.source', 'physical');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $receipt = $response->json();
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
        foreach (['/admin/credentials', '/admin/credentials/'.$receipt['credential']['id'], '/admin/students/'.$person->id] as $url) {
            $page = $this->get($url)->assertOk();
            $this->assertStringNotContainsString($receipt['payload'], $page->getContent());
            $this->assertStringNotContainsString($receipt['completion_nonce'], $page->getContent());
        }
        $this->postJson('/admin/credentials/issue', $data)->assertUnprocessable();
        $this->postJson('/admin/credentials/'.$receipt['credential']['id'].'/read-back', ['payload' => $receipt['payload'], 'completion_nonce' => $receipt['completion_nonce']])->assertOk();
        $this->postJson('/admin/credentials/'.$receipt['credential']['id'].'/activate', ['payload' => $receipt['payload'], 'completion_nonce' => $receipt['completion_nonce']])->assertOk()->assertJsonPath('credential.selected', true);
    }

    public function test_invalid_http_payloads_are_not_flashed_and_filters_do_not_redirect_loop(): void
    {
        $secret = app(CredentialToken::class)->generate();
        $this->post('/admin/credentials/check', ['payload' => str_repeat($secret, 10), 'completion_nonce' => $secret])->assertStatus(406);
        $this->postJson('/admin/credentials/check', ['payload' => str_repeat($secret, 10), 'completion_nonce' => $secret])->assertUnprocessable();
        $this->assertNull(session()->getOldInput('payload'));
        $this->assertNull(session()->getOldInput('completion_nonce'));
        $this->get('/admin/credentials?status=invalid')->assertUnprocessable();
        $this->get('/admin/credentials?q='.urlencode($secret))->assertUnprocessable();
    }

    public function test_guards_cannot_view_issue_or_inspect_credentials(): void
    {
        $this->signIn($this->user(['role' => 'guard']));
        foreach (['/admin/credentials', '/admin/credentials/create', '/admin/credentials/people'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->postJson('/admin/credentials/issue', [])->assertForbidden();
        $this->postJson('/admin/credentials/check', ['payload' => 'anything'])->assertForbidden();
    }

    public function test_simulator_requires_flag_loopback_local_environment_and_isolated_schema(): void
    {
        $this->get('/simulator')->assertNotFound();
        config(['nfcheck.nfc_simulator' => true]);
        $this->get('/simulator')->assertOk()->assertInertia(fn (Assert $page) => $page->component('credentials/Simulator')->where('app.simulator', true));
        $this->postJson('/simulator/check', ['payload' => 'invalid'])->assertOk()->assertJsonPath('reason', 'malformed')->assertJsonPath('simulated', true)->assertJsonPath('passage_recorded', false);
        $this->app['env'] = 'production';
        $this->get('/simulator')->assertNotFound();
        $this->app['env'] = 'testing';
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/simulator')->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        config(['database.connections.mysql.database' => 'nfcheck']);
        // Middleware must deny before opening a main-schema session or connection.
        $this->get('/simulator')->assertNotFound();
        config(['database.connections.mysql.database' => 'nfcheck_testing']);
    }
}
