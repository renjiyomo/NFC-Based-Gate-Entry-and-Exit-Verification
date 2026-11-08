<?php

declare(strict_types=1);

use App\Enums\PersonType;
use App\Models\Person;
use App\Models\User;
use App\Services\AccessManagement;
use App\Services\AccountService;
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
foreach ([['SIM-STUDENT-01', PersonType::Student, ['program' => 'Simulation program', 'year_level' => 1, 'academic_term' => 'Local lab']], ['SIM-EMPLOYEE-01', PersonType::Employee, ['department' => 'Simulation department', 'position' => 'Local lab']]] as [$number, $type, $profile]) {
    if (Person::withTrashed()->where('campus_number', $number)->exists()) {
        continue;
    }
    $person = app(PersonManagement::class)->save($operator, $type, ['campus_number' => $number, 'full_name' => $type === PersonType::Student ? 'SIMULATED Student One' : 'SIMULATED Employee One', 'status' => 'active', ...$profile]);
    app(AccessManagement::class)->grant($operator, $person, ['record_version' => $person->record_version, 'starts_at' => now('Asia/Manila')->subMinute()->format('Y-m-d\TH:i'), 'ends_at' => null, 'reason' => 'Synthetic local simulator grant']);
}
echo "Simulator operator and two synthetic people ready; existing lab records preserved. Credentials are in the private handoff file.\n";
