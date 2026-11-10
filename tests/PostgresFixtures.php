<?php

namespace Tests;

use App\Enums\PersonType;
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\User;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\PersonManagement;
use App\Support\DatabaseIdentity;
use Illuminate\Support\Str;

final class PostgresFixtures
{
    public static function create(): array
    {
        if (! preg_match('/^nfcheck_probe_[a-f0-9]{16}$/D', DatabaseIdentity::name())) {
            throw new \RuntimeException('Synthetic fixtures require the owned probe schema.');
        }
        $admin = User::create(['name' => 'Synthetic PostgreSQL administrator', 'email' => Str::uuid().'@example.invalid', 'username' => 'pg-'.Str::random(12), 'password' => 'synthetic validation password 496', 'role' => 'admin']);
        $person = app(PersonManagement::class)->save($admin, PersonType::Student, ['campus_number' => 'PG-'.Str::random(12), 'full_name' => 'Synthetic PostgreSQL Student', 'status' => 'active', 'program' => 'BSCS', 'year_level' => 2, 'academic_year' => '2026-2027', 'semester' => '1st Semester']);
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => 'PG-'.Str::random(12), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'issuer', 'physical');
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $service->activate($admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $gate = Gate::create(['code' => 'PG-'.Str::random(12), 'name' => 'Synthetic PostgreSQL Gate', 'status' => 'active']);
        $guard = User::create(['name' => $gate->name, 'email' => Str::uuid().'@example.invalid', 'username' => 'gate-'.Str::random(12), 'password' => 'synthetic validation password 496', 'role' => 'guard', 'gate_id' => $gate->id]);

        return ['admin_id' => $admin->id, 'person_id' => $person->id, 'credential_id' => $credential->id, 'gate_id' => $gate->id, 'guard_id' => $guard->id, 'payload' => $receipt['payload']];
    }
}
