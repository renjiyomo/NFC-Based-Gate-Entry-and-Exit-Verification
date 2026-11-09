<?php

declare(strict_types=1);

use App\Enums\PersonType;
use App\Models\Gate;
use App\Models\Person;
use App\Models\User;
use App\Services\AccountService;
use App\Services\GateManagement;
use App\Services\PersonManagement;
use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
foreach (Dotenv\Dotenv::parse(file_get_contents($root.'/.env.simulator')) as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('local') || ! config('nfcheck.nfc_simulator') || $connection['host'] !== '127.0.0.1' || (int) $connection['port'] !== 3307 || $connection['database'] !== 'nfcheck_simulator' || $connection['username'] !== 'nfcheck_simulator') {
    throw new RuntimeException('Refusing synthetic data outside the isolated simulator.');
}
$path = $root.'/.tools/secrets/simulator-account.json';
$operator = User::where('email', 'simulator@nfcheck.test')->first();
if (! $operator) {
    $password = 'Lab-'.bin2hex(random_bytes(20));
    $operator = app(AccountService::class)->create(['name' => 'Local simulator operator', 'email' => 'simulator@nfcheck.test', 'password' => $password, 'role' => 'admin', 'must_change_password' => false]);
    file_put_contents($path, json_encode(['url' => 'http://localhost:8002/login', 'email' => $operator->email, 'password' => $password, 'purpose' => 'Disposable local simulator only; unrelated to the three main administrators.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
}
foreach ([['SIM-STUDENT-01', PersonType::Student, ['program' => 'BSCS', 'year_level' => 1, 'academic_year' => '2026-2027', 'semester' => '1st Semester']], ['SIM-EMPLOYEE-01', PersonType::Employee, ['department' => 'Computer Studies Department', 'position' => 'Local lab']]] as [$number, $type, $profile]) {
    if (Person::withTrashed()->where('campus_number', $number)->exists()) {
        continue;
    }
    app(PersonManagement::class)->save($operator, $type, ['campus_number' => $number, 'full_name' => $type === PersonType::Student ? 'SIMULATED Student One' : 'SIMULATED Employee One', 'status' => 'active', ...$profile]);
}
$gate = Gate::withTrashed()->where('code', 'SIM-GATE-01')->first();
if (! $gate) {
    $gate = app(GateManagement::class)->save($operator, ['code' => 'SIM-GATE-01', 'name' => 'SIMULATED Lab Gate', 'status' => 'active']);
}
echo "Simulator administrator, synthetic people and lab gate ready. Existing records preserved; startup initializes the shared gate login next.\n";
