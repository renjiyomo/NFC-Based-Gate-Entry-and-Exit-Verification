<?php

declare(strict_types=1);

use App\Enums\PersonType;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\Person;
use App\Models\User;
use App\Services\AccessManagement;
use App\Services\AccountService;
use App\Services\GateManagement;
use App\Services\PersonManagement;
use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
foreach (Dotenv\Dotenv::parse(file_get_contents($root.'/.env.hardware')) as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('local') || ! config('nfcheck.hardware_lab') || config('nfcheck.nfc_simulator') || $connection['host'] !== '127.0.0.1' || (int) $connection['port'] !== 3307 || $connection['database'] !== 'nfcheck_hardware' || $connection['username'] !== 'nfcheck_hardware') {
    throw new RuntimeException('Refusing physical test fixtures outside the hardware workspace.');
}
$file = $root.'/.tools/secrets/hardware-accounts.json';
$handoff = is_file($file) ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
$accounts = [];
foreach (['admin' => 'Hardware test administrator', 'guard1' => 'Hardware test guard one', 'guard2' => 'Hardware test guard two'] as $key => $name) {
    $email = 'hardware.'.$key.'@nfcheck.test';
    $account = User::withTrashed()->where('email', $email)->first();
    if (! $account) {
        $password = 'Hardware-'.bin2hex(random_bytes(20));
        $account = app(AccountService::class)->create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => $key === 'admin' ? 'admin' : 'guard', 'must_change_password' => false], $accounts['admin'] ?? null);
        $handoff[$key] = ['email' => $email, 'password' => $password, 'url' => 'http://localhost:8003/login', 'purpose' => 'Synthetic identities for actual phone/tag testing only; no campus operations.'];
        file_put_contents($file, json_encode($handoff, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
    }
    $accounts[$key] = $account;
}
$admin = $accounts['admin'];
foreach ([['HW-STUDENT-01', PersonType::Student, ['program' => 'Hardware testing only', 'year_level' => 1]], ['HW-EMPLOYEE-01', PersonType::Employee, ['department' => 'Hardware testing only', 'position' => 'Synthetic identity']]] as [$number, $type, $profile]) {
    if (Person::withTrashed()->where('campus_number', $number)->exists()) {
        continue;
    }
    $person = app(PersonManagement::class)->save($admin, $type, ['campus_number' => $number, 'full_name' => $type === PersonType::Student ? 'HARDWARE TEST Student One' : 'HARDWARE TEST Employee One', 'status' => 'active', ...$profile]);
    app(AccessManagement::class)->grant($admin, $person, ['record_version' => $person->record_version, 'starts_at' => now('Asia/Manila')->subMinute()->format('Y-m-d\TH:i'), 'ends_at' => null, 'reason' => 'Synthetic physical NFC acceptance testing']);
}
$gate = Gate::withTrashed()->where('code', 'HW-GATE-01')->first();
if (! $gate) {
    $gate = app(GateManagement::class)->save($admin, ['code' => 'HW-GATE-01', 'name' => 'HARDWARE TEST Gate', 'status' => 'active']);
}
foreach ([$accounts['guard1'], $accounts['guard2']] as $guard) {
    if (! GuardGateAssignment::where('guard_id', $guard->id)->where('gate_id', $gate->id)->exists() && $guard->isGuard() && ! $gate->trashed() && $gate->status->value === 'active') {
        app(AccessManagement::class)->assign($admin, ['guard_id' => $guard->id, 'gate_id' => $gate->id, 'starts_at' => now('Asia/Manila')->subMinute()->format('Y-m-d\TH:i'), 'ends_at' => null]);
    }
}
echo "Hardware test accounts, synthetic people and gate are ready. No tags were generated or activated; sign-in details remain private.\n";
