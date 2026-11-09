<?php

namespace Tests\Feature;

use App\Models\AccessAuthorization;
use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Services\Credentials\CredentialLifecycle;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\ManagementTestCase;

class SimplifiedFlowTest extends ManagementTestCase
{
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
        $body = ['request_id' => (string) Str::uuid(), 'payload' => $payload, 'direction' => 'entry'];
        $a = $this->postJson('/guard/gates/'.$gate->id.'/scan', $body)->assertOk()->assertJsonPath('review.status', 'confirmed')->assertJsonPath('passage.identity_method', 'nfc_scan')->assertJsonPath('identity.name', $person->full_name)->json();
        $this->assertSame(1, AccessEvent::count());
        $this->assertSame(0, AccessAuthorization::count());
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());
        $this->postJson('/guard/gates/'.$gate->id.'/scan', $body)->assertOk()->assertJsonPath('passage.id', $a['passage']['id']);
        $this->assertSame(1, ScanAttempt::count());
        $this->getJson('/guard/gates/'.$gate->id.'/requests/'.$body['request_id'])->assertOk()->assertJsonPath('passage.id', $a['passage']['id']);
        $this->postJson('/guard/gates/'.$gate->id.'/scan', [...$body, 'direction' => 'exit'])->assertStatus(422);
        $this->postJson('/guard/gates/'.$gate->id.'/scan', [...$body, 'request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('review.reason', 'duplicate_passage')->assertJsonPath('passage', null);
        $this->assertSame(1, AccessEvent::count());
        $this->postJson('/guard/gates/'.$gate->id.'/scan', [...$body, 'request_id' => (string) Str::uuid(), 'direction' => 'exit'])->assertOk()->assertJsonPath('passage.direction', 'exit');
        $this->assertSame(2, AccessEvent::count());
        app(CredentialLifecycle::class)->terminate($this->admin, $credential->fresh(), 'revoke', 'lost');
        $this->postJson('/guard/gates/'.$gate->id.'/scan', [...$body, 'request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('verification.reason', 'revoked')->assertJsonPath('identity', null)->assertJsonPath('passage', null);
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
        $body = ['request_id' => (string) Str::uuid(), 'payload' => $payload, 'direction' => 'entry'];
        $this->postJson('/guard/gates/'.$other->id.'/scan', $body)->assertForbidden();
        $person->status = 'inactive';
        $person->save();
        $this->postJson('/guard/gates/'.$gate->id.'/scan', $body)->assertOk()->assertJsonPath('verification.reason', 'person_inactive')->assertJsonPath('identity', null);
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_malformed_academic_fields_are_validation_errors_and_not_server_errors(): void
    {
        foreach (['program', 'academic_year', 'semester', 'year_level'] as $field) {
            $this->post('/admin/students', $this->student([$field => ['unexpected']]))->assertSessionHasErrors($field);
        }
        $this->post('/admin/employees', $this->employee(['department' => ['unexpected']]))->assertSessionHasErrors('department');
    }
}
