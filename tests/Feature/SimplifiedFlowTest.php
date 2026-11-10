<?php

namespace Tests\Feature;

use App\Models\AccessAuthorization;
use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\AutomaticPassages;
use App\Services\Monitoring\PassageCorrections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ManagementTestCase;

class SimplifiedFlowTest extends ManagementTestCase
{
    // Historical manual-direction records remain readable/correctable.
    private function legacyScan($gate, $account, array $data): TestResponse
    {
        $result = app(AutomaticPassages::class)->scan($account, $gate, $data, app('session')->getId(), 'physical');

        return TestResponse::fromBaseResponse(response()->json($result));
    }

    private function station(): array
    {
        $gate = Gate::create(['code' => 'AUTO-'.Str::random(8), 'name' => 'Synthetic gate', 'status' => 'active']);
        $account = $this->user(['role' => 'guard', 'username' => 'gate-'.Str::random(12), 'gate_id' => $gate->id]);

        return [$gate, $account];
    }

    private function coin(): array
    {
        $person = $this->makePerson();
        $service = app(CredentialLifecycle::class);
        $r = $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => 'AUTO-'.Str::random(12), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'issuer', 'physical');
        $credential = NfcCredential::where('public_id', $r['credential']['id'])->sole();
        $service->readBack($this->admin, $credential, 'issuer', $r['payload'], $r['completion_nonce'], 'physical');
        $service->activate($this->admin, $credential, 'issuer', $r['payload'], $r['completion_nonce'], 'physical');

        return [$person, $credential, $r['payload']];
    }

    public function test_gate_and_login_are_saved_atomically_without_email_or_schedules(): void
    {
        Mail::fake();
        $this->post('/admin/gates', ['code' => 'NEW-GATE', 'name' => 'Gate One', 'status' => 'active', 'username' => 'gate.one', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertRedirect();
        $gate = Gate::where('code', 'NEW-GATE')->sole();
        $account = $gate->account;
        $this->assertSame('gate.one', $account->username);
        $this->assertFalse($account->must_change_password);
        $this->assertSame(0, $account->guardAssignments()->count());
        Mail::assertNothingSent();
        $this->post('/logout');
        $this->post('/login', ['username' => 'gate.one', 'password' => self::PASSWORD])->assertRedirect('/guard/scanner');
        $this->assertAuthenticatedAs($account);
        $this->get('/guard/scanner')->assertOk()->assertInertia(fn (Assert $p) => $p->where('station.id', $gate->id));
    }

    public function test_catalog_fields_reject_arbitrary_values_and_save_year_and_semester(): void
    {
        $this->get('/admin/students/create')->assertInertia(fn (Assert $p) => $p->has('catalog.programs', 17)->has('catalog.departments', 6));
        foreach ([['year_level' => 5], ['year_level' => 999], ['program' => 'Invented program'], ['academic_year' => '2026-2029'], ['semester' => 'Unknown']] as $bad) {
            $this->post('/admin/students', $this->student($bad))->assertSessionHasErrors(array_key_first($bad));
        }
        $this->post('/admin/employees', $this->employee(['department' => 'Invented department']))->assertSessionHasErrors('department');
        $data = $this->student();
        $this->post('/admin/students', $data)->assertRedirect();
        $p = Person::where('campus_number', $data['campus_number'])->sole();
        $this->assertSame('2026-2027', $p->studentProfile->academic_year);
        $this->assertSame('1st Semester', $p->studentProfile->semester);
        $this->assertSame(0, AccessAuthorization::count());
    }

    public function test_scan_records_once_without_grant_assignment_or_manual_confirmation(): void
    {
        [$gate,$account] = $this->station();
        [$person,$credential,$payload] = $this->coin();
        $this->signIn($account);
        $body = ['request_id' => (string) Str::uuid(), 'payload' => $payload];
        $a = $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', $body)->assertOk()->assertJsonPath('review.status', 'confirmed')->assertJsonPath('passage.identity_method', 'nfc_scan')->assertJsonPath('identity.name', $person->full_name)->json();
        $this->assertSame(1, AccessEvent::count());
        $this->assertSame(0, AccessAuthorization::count());
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
        $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', $body)->assertOk()->assertJsonPath('passage.id', $a['passage']['id']);
        $this->assertSame(1, ScanAttempt::count());
        $this->getJson('/guard/gates/'.$gate->id.'/requests/'.$body['request_id'])->assertOk()->assertJsonPath('passage.id', $a['passage']['id']);
        $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', [...$body, 'direction' => 'exit'])->assertStatus(422);
        $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', [...$body, 'request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('review.reason', 'duplicate_passage')->assertJsonPath('passage', null);
        $this->assertSame(1, AccessEvent::count());
        $this->travel(10)->seconds();
        $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', [...$body, 'request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('passage.direction', 'exit');
        $this->assertSame(2, AccessEvent::count());
        app(CredentialLifecycle::class)->terminate($this->admin, $credential->fresh(), 'revoke', 'lost');
        $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', [...$body, 'request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('verification.reason', 'revoked')->assertJsonPath('identity', null)->assertJsonPath('passage', null);
        $this->assertSame(2, AccessEvent::count());
        $this->travel(31)->minutes();
        $this->getJson('/guard/gates/'.$gate->id.'/requests/'.$body['request_id'])->assertOk()->assertJsonPath('identity', null)->assertJsonPath('passage.id', $a['passage']['id']);
    }

    public function test_account_cannot_scan_another_gate_and_inactive_people_cannot_create_events(): void
    {
        [$gate,$account] = $this->station();
        [$other] = $this->station();
        [$person,,$payload] = $this->coin();
        $this->signIn($account);
        $body = ['request_id' => (string) Str::uuid(), 'payload' => $payload];
        $this->postJson('/guard/gates/'.$other->id.'/automatic-scan', $body)->assertForbidden();
        $person->status = 'inactive';
        $person->save();
        $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', $body)->assertOk()->assertJsonPath('verification.reason', 'person_inactive')->assertJsonPath('identity', null);
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_malformed_academic_fields_are_validation_errors_and_not_server_errors(): void
    {
        foreach (['program', 'academic_year', 'semester', 'year_level'] as $field) {
            $this->post('/admin/students', $this->student([$field => ['unexpected']]))->assertSessionHasErrors($field);
        }
        $this->post('/admin/employees', $this->employee(['department' => ['unexpected']]))->assertSessionHasErrors('department');
    }

    public static function sequences(): array
    {
        return [
            'first entry' => [null, 'entry', false, null],
            'first exit' => [null, 'exit', false, 'exit_without_recorded_entry'],
            'same-day repeated entry' => ['entry', 'entry', false, 'repeated_entry'],
            'same-day repeated exit' => ['exit', 'exit', false, 'repeated_exit'],
            'same-day completed visit' => ['entry', 'exit', false, null],
            'same-day return' => ['exit', 'entry', false, null],
            'new-day unclosed entry' => ['entry', 'entry', true, 'previous_day_unclosed_entry'],
            'overnight exit' => ['entry', 'exit', true, 'exit_after_earlier_day_entry'],
            'new-day repeated exit' => ['exit', 'exit', true, 'exit_without_entry_today'],
            'new-day return' => ['exit', 'entry', true, null],
        ];
    }

    #[DataProvider('sequences')]
    public function test_sequence_warnings_record_real_scans_without_inventing_or_blocking_passages(?string $previous, string $direction, bool $nextDay, ?string $notice): void
    {
        $start = CarbonImmutable::parse('2026-11-09T15:59:40Z'); // 23:59 Manila; crossing midnight leaves the UTC date unchanged.
        $this->travelTo($start);
        [$gate, $account] = $this->station();
        [$person, , $payload] = $this->coin();
        $this->signIn($account);
        if ($previous) {
            $this->legacyScan($gate, $account, ['request_id' => (string) Str::uuid(), 'payload' => $payload, 'direction' => $previous])->assertOk();
        }
        $this->travelTo($start->addSeconds($nextDay ? 30 : 12));
        $request = (string) Str::uuid();
        $response = $this->legacyScan($gate, $account, ['request_id' => $request, 'payload' => $payload, 'direction' => $direction])
            ->assertOk()->assertJsonPath('passage.direction', $direction)->assertJsonPath('passage.sequence_notice', $notice)->assertJsonPath('review.status', 'confirmed');
        $this->assertSame($previous ? 2 : 1, AccessEvent::count());
        $event = AccessEvent::where('public_id', $response->json('passage.id'))->sole();
        $this->assertSame($notice, $event->sequence_notice);
        if ($notice && $previous) {
            $response->assertJsonPath('sequence_context.direction', $previous)->assertJsonPath('sequence_context.gate', $gate->code);
        } else {
            $response->assertJsonPath('sequence_context', null);
        }
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
        $this->getJson('/guard/gates/'.$gate->id.'/activity')->assertOk()->assertJsonPath('records.0.sequence_notice', $notice);
        $this->getJson('/guard/gates/'.$gate->id.'/requests/'.$request)->assertOk()->assertJsonPath('passage.sequence_notice', $notice)->assertJsonPath('passage.id', $event->public_id);
        $this->assertSame($previous ? 2 : 1, AccessEvent::count());
    }

    public function test_midnight_does_not_reset_duplicate_window_and_voided_history_is_ignored(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09T15:59:57Z'));
        [$gate, $account] = $this->station();
        [, , $payload] = $this->coin();
        $this->signIn($account);
        $body = ['payload' => $payload, 'direction' => 'entry'];
        $this->legacyScan($gate, $account, [...$body, 'request_id' => (string) Str::uuid()])->assertOk();
        $event = AccessEvent::sole();
        $this->travel(5)->seconds();
        $this->legacyScan($gate, $account, [...$body, 'request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('review.reason', 'duplicate_passage')->assertJsonPath('passage', null);
        app(PassageCorrections::class)->append($this->admin, $event, ['expected_revision' => 0, 'action' => 'void', 'reason' => 'Synthetic wrong-direction correction'], 'physical');
        $this->travel(12)->seconds();
        $this->legacyScan($gate, $account, [...$body, 'request_id' => (string) Str::uuid(), 'direction' => 'exit'])->assertOk()->assertJsonPath('passage.sequence_notice', 'exit_without_recorded_entry')->assertJsonPath('sequence_context', null);
        $this->assertSame(2, AccessEvent::count());
        $this->assertSame(1, AccessEvent::effective()->count());
    }

    public function test_lost_coin_stops_immediately_and_failed_replacement_never_reactivates_it(): void
    {
        [$gate, $account] = $this->station();
        [$person, $old, $oldPayload] = $this->coin();
        $this->postJson('/admin/credentials/'.$old->public_id.'/revoke', ['reason' => 'lost'])->assertOk()->assertJsonPath('credential.status', 'revoked');
        $this->assertNull($person->fresh()->active_credential_assignment_id);
        $this->signIn($account);
        $scan = fn ($payload) => $this->postJson('/guard/gates/'.$gate->id.'/automatic-scan', ['request_id' => (string) Str::uuid(), 'payload' => $payload]);
        $scan($oldPayload)->assertOk()->assertJsonPath('verification.reason', 'revoked')->assertJsonPath('identity', null)->assertJsonPath('passage', null);
        $this->assertSame(0, AccessEvent::count());
        $service = app(CredentialLifecycle::class);
        $issue = fn () => $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->fresh()->record_version, 'label' => 'LOST-'.Str::random(12), 'mode' => 'lost', 'replaces_id' => $old->public_id, 'request_id' => (string) Str::uuid()], 'lost-replacement', 'physical');
        $failed = $issue();
        $service->terminate($this->admin, NfcCredential::where('public_id', $failed['credential']['id'])->sole(), 'cancel', 'issuance_cancelled');
        $this->assertSame('revoked', $old->fresh()->status);
        $this->assertNull($person->fresh()->active_credential_assignment_id);
        $receipt = $issue();
        $new = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $scan($receipt['payload'])->assertOk()->assertJsonPath('verification.reason', 'pending')->assertJsonPath('passage', null);
        $service->readBack($this->admin, $new, 'lost-replacement', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $service->activate($this->admin, $new, 'lost-replacement', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $this->assertNotSame($oldPayload, $receipt['payload']);
        $scan($oldPayload)->assertOk()->assertJsonPath('verification.reason', 'revoked')->assertJsonPath('identity', null)->assertJsonPath('passage', null);
        $scan($receipt['payload'])->assertOk()->assertJsonPath('passage.direction', 'entry')->assertJsonPath('identity.name', $person->full_name);
        $this->assertSame(1, AccessEvent::count());
        $this->assertSame('lost', $old->fresh()->close_reason);
        $this->assertNotNull($old->assignment->fresh()->ended_at);
    }

    public function test_person_record_keeps_current_coin_visible_after_many_cancelled_replacements(): void
    {
        [$person, $current] = $this->coin();
        $service = app(CredentialLifecycle::class);
        for ($i = 0; $i < 11; $i++) {
            $pending = $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->fresh()->record_version, 'label' => 'CANCELLED-'.Str::random(12), 'mode' => 'replace', 'replaces_id' => $current->public_id, 'request_id' => (string) Str::uuid()], 'cancelled-replacement', 'physical');
            $service->terminate($this->admin, NfcCredential::where('public_id', $pending['credential']['id'])->sole(), 'cancel', 'issuance_cancelled');
        }
        $this->get('/admin/students/'.$person->id)->assertInertia(fn (Assert $p) => $p->has('credentials', 10)->where('credentials.0.id', $current->public_id)->where('credentials.0.selected', true));
        $this->get('/admin/credentials/create?person_id='.$person->id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('selectedPerson.id', $person->id)->where('mode', 'replace'));
    }
}
