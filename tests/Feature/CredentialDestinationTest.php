<?php

namespace Tests\Feature;

use App\Models\NfcCredential;
use App\Models\Person;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\Credentials\CredentialToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\ManagementTestCase;

class CredentialDestinationTest extends ManagementTestCase
{
    private function issue(Person $person, string $source = 'physical'): array
    {
        return app(CredentialLifecycle::class)->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->fresh()->record_version, 'label' => 'DEST-'.Str::uuid(), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'destination-test', $source);
    }

    private function activate(array $receipt): NfcCredential
    {
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service = app(CredentialLifecycle::class);
        $service->readBack($this->admin, $credential, 'destination-test', $receipt['payload'], $receipt['completion_nonce'], $credential->source);

        return $service->activate($this->admin, $credential, 'destination-test', $receipt['payload'], $receipt['completion_nonce'], $credential->source);
    }

    public function test_empty_coin_check_creates_no_credential_or_passage(): void
    {
        $this->postJson('/admin/credentials/destination', ['kind' => 'empty'])->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('reason', 'empty')->assertJsonPath('credential', null)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(0, NfcCredential::count());
        $this->assertSame(0, DB::table('access_events')->count());
    }

    public function test_active_coin_is_blocked_and_owner_identified_even_without_campus_access(): void
    {
        $person = $this->makePerson();
        $receipt = $this->issue($person);
        $credential = $this->activate($receipt);
        $person->update(['status' => 'inactive']);
        $response = $this->postJson('/admin/credentials/destination', ['kind' => 'reference', 'payload' => $receipt['payload']])->assertOk()
            ->assertJsonPath('allowed', false)->assertJsonPath('reason', 'registered')->assertJsonPath('credential.label', $credential->label)->assertJsonPath('credential.person.number', $person->campus_number);
        $this->assertStringNotContainsString($receipt['payload'], $response->getContent());
        $this->assertSame('active', $credential->fresh()->status);
        $this->assertSame($credential->assignment->id, $person->fresh()->active_credential_assignment_id);
    }

    public function test_pending_coin_is_blocked_and_explicitly_retired_coin_can_be_reused(): void
    {
        $receipt = $this->issue($this->makePerson());
        $request = ['kind' => 'reference', 'payload' => $receipt['payload']];
        $this->postJson('/admin/credentials/destination', $request)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('credential.status', 'pending');
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        app(CredentialLifecycle::class)->terminate($this->admin, $credential, 'cancel', 'issuance_cancelled');
        $this->postJson('/admin/credentials/destination', $request)->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('reason', 'retired')->assertJsonPath('credential.status', 'cancelled');
        $this->assertSame('cancelled', $credential->fresh()->status);
        $this->assertNull($credential->assignment->person->active_credential_assignment_id);
    }

    public function test_unknown_unrelated_or_cross_source_data_is_not_approved_for_overwrite(): void
    {
        $this->postJson('/admin/credentials/destination', ['kind' => 'other'])->assertOk()->assertJsonPath('allowed', false);
        $this->postJson('/admin/credentials/destination', ['kind' => 'reference', 'payload' => app(CredentialToken::class)->generate()])->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('credential', null);
        $receipt = $this->issue($this->makePerson(), 'simulated');
        $this->postJson('/admin/credentials/destination', ['kind' => 'reference', 'payload' => $receipt['payload']])->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('credential', null);
        $this->postJson('/admin/credentials/destination', ['kind' => 'reference'])->assertUnprocessable();
    }

    public function test_effectively_expired_reference_can_be_reused_without_mutating_its_history(): void
    {
        $receipt = $this->issue($this->makePerson());
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $this->travelTo($credential->pending_expires_at);
        $this->postJson('/admin/credentials/destination', ['kind' => 'reference', 'payload' => $receipt['payload']])->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('credential.status', 'expired');
        $this->assertSame('pending', $credential->fresh()->status);
        $this->assertNull($credential->fresh()->closed_at);
    }

    public function test_guard_cannot_identify_coin_owners_and_simulator_stays_isolated(): void
    {
        $this->signIn($this->user(['role' => 'guard']))->postJson('/admin/credentials/destination', ['kind' => 'empty'])->assertForbidden();
        $this->signIn($this->admin);
        $this->postJson('/simulator/destination', ['kind' => 'empty'])->assertNotFound();
        config(['nfcheck.nfc_simulator' => true]);
        $this->postJson('/simulator/destination', ['kind' => 'empty'])->assertOk()->assertJsonPath('allowed', true);
    }

    public function test_registry_defaults_to_current_references_and_keeps_closed_history_accessible(): void
    {
        $active = $this->issue($this->makePerson());
        $this->activate($active);
        $closed = $this->issue($this->makePerson());
        app(CredentialLifecycle::class)->terminate($this->admin, NfcCredential::where('public_id', $closed['credential']['id'])->sole(), 'cancel', 'issuance_cancelled');
        $pending = $this->issue($this->makePerson());
        $this->get('/admin/credentials')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.status', 'current')->has('records.data', 2));
        $this->get('/admin/credentials?status=all')->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data', 3));
        $this->assertSame('pending', NfcCredential::where('public_id', $pending['credential']['id'])->value('status'));
    }

    public function test_person_status_and_current_nfc_credential_status_are_independent(): void
    {
        $student = $this->makePerson();
        $receipt = $this->issue($student);
        $credential = $this->activate($receipt);
        $this->get('/admin/students?q='.$student->campus_number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.data.0.status', 'active')->where('records.data.0.nfc_credential.label', $credential->label));
        app(CredentialLifecycle::class)->terminate($this->admin, $credential, 'revoke', 'withdrawn');
        $this->get('/admin/students?q='.$student->campus_number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.data.0.status', 'active')->where('records.data.0.nfc_credential', null));
    }
}
