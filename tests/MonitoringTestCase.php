<?php

namespace Tests;

use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\GateOperations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

abstract class MonitoringTestCase extends ManagementTestCase
{
    protected User $guard;

    protected Gate $gate;

    protected array $receipts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
        $this->guard = $this->user(['role' => 'guard']);
        $this->gate = Gate::create(['code' => 'REPORT-'.Str::random(8), 'name' => 'Synthetic reporting station', 'status' => 'active']);
        GuardGateAssignment::create(['guard_id' => $this->guard->id, 'gate_id' => $this->gate->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subYear(), 'ends_at' => now()->addYear()]);
    }

    protected function subject(string $kind = 'students', string $source = 'physical', array $attributes = []): Person
    {
        $person = $this->makePerson($kind, $attributes);
        $person->authorizations()->create(['status' => 'active', 'valid_from' => now()->subYear(), 'granted_by' => $this->admin->id]);
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => 'REPORT-'.Str::uuid(), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'report-issuer', $source);
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($this->admin, $credential, 'report-issuer', $receipt['payload'], $receipt['completion_nonce'], $source);
        $service->activate($this->admin, $credential, 'report-issuer', $receipt['payload'], $receipt['completion_nonce'], $source);
        $this->receipts[$person->id] = $receipt;

        return $person;
    }

    protected function scanPerson(Person $person, string $at, string $direction = 'entry', string $source = 'physical'): array
    {
        $this->travelTo(CarbonImmutable::parse($at, 'UTC'));

        return app(GateOperations::class)->scan($this->guard, $this->gate, ['payload' => $this->receipts[$person->id]['payload'], 'request_id' => (string) Str::uuid(), 'direction' => $direction], 'report-guard', $source);
    }

    protected function passage(Person $person, string $at, string $direction = 'entry', string $source = 'physical'): AccessEvent
    {
        $scan = $this->scanPerson($person, $at, $direction, $source);
        $attempt = ScanAttempt::where('public_id', $scan['id'])->sole();
        app(GateOperations::class)->review($this->guard, $this->gate, $attempt, ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'], 'report-guard', $source);

        return AccessEvent::where('scan_attempt_id', $attempt->id)->sole();
    }

    protected function finishTime(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 04:00:00', 'UTC'));
    }
}
