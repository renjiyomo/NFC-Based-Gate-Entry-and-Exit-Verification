<?php

declare(strict_types=1);

use App\Enums\PersonType;
use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\NfcCredential;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\GateOperations;
use App\Services\PersonManagement;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
foreach (Dotenv\Dotenv::parse(file_get_contents($root.'/.env.testing')) as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('testing') || $connection['host'] !== '127.0.0.1' || (int) $connection['port'] !== 3307 || $connection['database'] !== 'nfcheck_testing' || $connection['username'] !== 'nfcheck_test') {
    throw new RuntimeException('Concurrency tests require the fixed isolated testing connection.');
}
$identity = DB::selectOne('SELECT VERSION() version, @@datadir directory, DATABASE() db');
if ($identity->version !== '8.4.13' || $identity->db !== 'nfcheck_testing' || strcasecmp(rtrim(str_replace('\\', '/', $identity->directory), '/'), str_replace('\\', '/', realpath($root.'/.tools/mysql-data'))) !== 0) {
    throw new RuntimeException('Unexpected test instance.');
}

if (($argv[1] ?? '') === '--worker') {
    try {
        $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        echo "READY\n";
        flush();
        $actor = User::findOrFail($input['actor']);
        $gate = Gate::findOrFail($input['gate']);
        $operations = app(GateOperations::class);
        if ($input['action'] === 'revoke') {
            app(CredentialLifecycle::class)->terminate($actor, NfcCredential::findOrFail($input['credential']), 'revoke', 'lost');
            echo json_encode(['revoked' => true], JSON_THROW_ON_ERROR);
            exit(0);
        }
        if ($input['action'] === 'scan_confirm') {
            $result = $operations->scan($actor, $gate, ['request_id' => $input['request'], 'payload' => $input['payload'], 'direction' => 'entry'], $input['session'], 'physical');
            $attempt = ScanAttempt::where('public_id', $result['id'])->sole();
        } else {
            $attempt = ScanAttempt::findOrFail($input['attempt']);
        }
        $result = $operations->review($actor, $gate, $attempt, ['action' => $input['action'] === 'cancel' ? 'cancel' : 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'], $input['session'], 'physical');
        echo json_encode(['id' => $result['id'], 'status' => $result['review']['status'], 'passage' => $result['passage']['id'] ?? null, 'duplicate' => $result['duplicate_of']['id'] ?? null], JSON_THROW_ON_ERROR);
        exit(0);
    } catch (Throwable $error) {
        echo json_encode(['error' => $error::class], JSON_THROW_ON_ERROR);
        exit(1);
    }
}

$secrets = json_decode(file_get_contents($root.'/.tools/secrets/database.json'), true, 512, JSON_THROW_ON_ERROR);
$maintenance = new PDO('mysql:host=127.0.0.1;port=3307;dbname=nfcheck_testing;charset=utf8mb4', 'root', $secrets['root'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = ['users', 'persons', 'gates', 'guard_gate_assignments', 'access_authorizations', 'student_profiles', 'nfc_credentials', 'credential_assignments', 'scan_attempts', 'access_events', 'audit_logs'];
$counts = static fn (): array => array_combine($tables, array_map(static fn (string $table): int => (int) $maintenance->query('SELECT COUNT(*) FROM nfcheck_testing.'.$table)->fetchColumn(), $tables));
$before = $counts();
$ownedUsers = [];
$ownedPeople = [];
$ownedGate = null;
$results = [];
$children = [];
$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$run = static function (array $inputs, string $lockTable, int $lockId) use ($root, $maintenance, &$children, $check): array {
    DB::beginTransaction();
    DB::table($lockTable)->where('id', $lockId)->lockForUpdate()->first();
    $workers = [];
    try {
        foreach ($inputs as $input) {
            $worker = new Process([PHP_BINARY, __FILE__, '--worker'], $root);
            $worker->setInput(json_encode($input, JSON_THROW_ON_ERROR)); // References travel only through anonymous stdin.
            $worker->setTimeout(45);
            $worker->start();
            $workers[] = $worker;
            $children[] = $worker;
        }
        $deadline = microtime(true) + 12;
        do {
            $waiters = (int) $maintenance->query("SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE = w.ENGINE AND l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID WHERE l.OBJECT_SCHEMA = 'nfcheck_testing' AND l.OBJECT_NAME = '".$lockTable."'")->fetchColumn();
            if ($waiters >= 2) {
                break;
            }
            foreach ($workers as $worker) {
                $check($worker->isRunning(), 'A worker ended before both transactions overlapped.');
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $check($waiters >= 2, 'Could not establish two concurrent blocked transactions.');
    } finally {
        DB::rollBack();
    }
    $outputs = [];
    foreach ($workers as $worker) {
        $worker->wait();
        $check($worker->isSuccessful(), 'Concurrency worker failed; no input was printed.');
        $outputs[] = json_decode(trim(str_replace("READY\n", '', str_replace("\r", '', $worker->getOutput()))), true, 512, JSON_THROW_ON_ERROR);
    }

    return $outputs;
};

try {
    $suffix = (string) Str::uuid();
    foreach (['admin', 'guard', 'guard'] as $index => $role) {
        $user = User::create(['name' => 'Synthetic concurrency '.$role, 'email' => 'race-'.$suffix.'-'.$index.'@example.invalid', 'password' => bin2hex(random_bytes(24)), 'role' => $role]);
        $ownedUsers[] = $user->id;
    }
    [$admin, $guard1, $guard2] = array_map(fn ($id) => User::findOrFail($id), $ownedUsers);
    $gate = Gate::create(['code' => 'RACE-'.Str::random(10), 'name' => 'Synthetic concurrency gate', 'status' => 'active']);
    $ownedGate = $gate->id;
    foreach ([$guard1, $guard2] as $guard) {
        GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'assigned_by' => $admin->id, 'starts_at' => now()->subMinute()]);
    }
    $fixture = static function () use ($admin, &$ownedPeople): array {
        $person = app(PersonManagement::class)->save($admin, PersonType::Student, ['campus_number' => 'RACE-'.Str::random(10), 'full_name' => 'Synthetic concurrency person', 'status' => 'active', 'program' => 'Test only']);
        $ownedPeople[] = $person->id;
        $person->authorizations()->create(['status' => 'active', 'valid_from' => now()->subMinute(), 'granted_by' => $admin->id]);
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => 'RACE-'.Str::random(14), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'race-issuer', 'physical');
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($admin, $credential, 'race-issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $service->activate($admin, $credential, 'race-issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');

        return [$person, $receipt, $credential];
    };
    $scan = static fn (User $guard, array $receipt, string $session): array => app(GateOperations::class)->scan($guard, $gate, ['request_id' => (string) Str::uuid(), 'payload' => $receipt['payload'], 'direction' => 'entry'], $session, 'physical');
    $input = static fn (User $guard, array $attempt, string $session, string $action = 'confirm'): array => ['actor' => $guard->id, 'gate' => $gate->id, 'attempt' => ScanAttempt::where('public_id', $attempt['id'])->value('id'), 'session' => $session, 'action' => $action];

    [$person, $receipt] = $fixture();
    $a = $scan($guard1, $receipt, 'race-one');
    $b = $scan($guard2, $receipt, 'race-two');
    $output = $run([$input($guard1, $a, 'race-one'), $input($guard2, $b, 'race-two')], 'persons', $person->id);
    $check(AccessEvent::where('person_id', $person->id)->count() === 1 && count(array_filter($output, fn ($row) => $row['duplicate'] !== null)) === 1, 'Concurrent confirmations did not produce exactly one passage plus one duplicate.');
    $results[] = 'two guards / different request IDs: one passage, one suppressed duplicate';

    [$person, $receipt] = $fixture();
    $request = (string) Str::uuid();
    $shared = ['actor' => $guard1->id, 'gate' => $gate->id, 'action' => 'scan_confirm', 'session' => 'same-request', 'request' => $request, 'payload' => $receipt['payload']];
    $output = $run([$shared, $shared], 'users', $guard1->id);
    $check(ScanAttempt::where('person_id', $person->id)->count() === 1 && AccessEvent::where('person_id', $person->id)->count() === 1 && $output[0]['id'] === $output[1]['id'] && $output[0]['passage'] === $output[1]['passage'], 'Same-request concurrent retry was not idempotent.');
    $results[] = 'same guard / same request ID: one attempt and one passage';

    [$person, $receipt] = $fixture();
    $attempt = $scan($guard1, $receipt, 'race-cancel');
    $run([$input($guard1, $attempt, 'race-cancel'), $input($guard1, $attempt, 'race-cancel', 'cancel')], 'users', $guard1->id);
    $status = ScanAttempt::where('public_id', $attempt['id'])->sole()->review_status;
    $check(in_array($status, ['confirmed', 'cancelled'], true) && AccessEvent::where('person_id', $person->id)->count() === ($status === 'confirmed' ? 1 : 0), 'Confirm/cancel race violated terminal review state.');
    $results[] = 'confirmation versus cancellation: one terminal result consistent with passage';

    [$person, $receipt, $credential] = $fixture();
    $attempt = $scan($guard1, $receipt, 'race-revoke');
    $run([$input($guard1, $attempt, 'race-revoke'), ['actor' => $admin->id, 'gate' => $gate->id, 'credential' => $credential->id, 'action' => 'revoke']], 'persons', $person->id);
    $check($credential->fresh()->status === 'revoked' && AccessEvent::where('person_id', $person->id)->count() <= 1, 'Concurrent revocation failed.');
    $event = AccessEvent::where('person_id', $person->id)->first();
    if ($event) {
        $passageAudit = DB::table('audit_logs')->where('action', 'passage.recorded')->where('target_id', $event->id)->value('id');
        $revocationAudit = DB::table('audit_logs')->where('action', 'credential.revoked')->where('target_id', $credential->id)->value('id');
        $check($passageAudit && $revocationAudit && $passageAudit < $revocationAudit, 'A passage was recorded after revocation.');
    } else {
        $check(ScanAttempt::where('public_id', $attempt['id'])->value('review_status') === 'cancelled', 'Revocation did not close the stale review.');
    }
    $results[] = 'confirmation versus revocation: no passage after committed revocation';
} finally {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach ($children as $child) {
        if ($child->isRunning()) {
            $child->wait();
        }
    }
    // Maintenance cleanup is limited to this run's captured fixture IDs, in the
    // verified testing schema. Runtime accounts cannot delete immutable history.
    if ($ownedUsers) {
        $maintenance->beginTransaction();
        $execute = static function (string $sql, array $ids) use ($maintenance): void {
            if (! $ids) {
                return;
            } $statement = $maintenance->prepare(str_replace(':ids', implode(',', array_fill(0, count($ids), '?')), $sql));
            $statement->execute($ids);
        };
        $execute('UPDATE nfcheck_testing.scan_attempts SET duplicate_of_event_id = NULL WHERE guard_id IN (:ids)', $ownedUsers);
        $execute('DELETE FROM nfcheck_testing.access_events WHERE guard_id IN (:ids)', $ownedUsers);
        $execute('DELETE FROM nfcheck_testing.scan_attempts WHERE guard_id IN (:ids)', $ownedUsers);
        $execute('UPDATE nfcheck_testing.persons SET active_credential_assignment_id = NULL WHERE id IN (:ids)', $ownedPeople);
        $execute('DELETE FROM nfcheck_testing.credential_assignments WHERE person_id IN (:ids)', $ownedPeople);
        $execute('DELETE FROM nfcheck_testing.nfc_credentials WHERE created_by IN (:ids)', $ownedUsers);
        $execute('DELETE FROM nfcheck_testing.access_authorizations WHERE person_id IN (:ids)', $ownedPeople);
        $execute('DELETE FROM nfcheck_testing.student_profiles WHERE person_id IN (:ids)', $ownedPeople);
        $execute('DELETE FROM nfcheck_testing.persons WHERE id IN (:ids)', $ownedPeople);
        $execute('DELETE FROM nfcheck_testing.guard_gate_assignments WHERE guard_id IN (:ids)', $ownedUsers);
        $execute('DELETE FROM nfcheck_testing.gates WHERE id IN (:ids)', $ownedGate ? [$ownedGate] : []);
        $execute('DELETE FROM nfcheck_testing.audit_logs WHERE actor_id IN (:ids)', $ownedUsers);
        $execute('DELETE FROM nfcheck_testing.users WHERE id IN (:ids)', $ownedUsers);
        $maintenance->commit();
    }
}
$check($before === $counts(), 'Testing row counts were not restored after scoped cleanup.');
echo json_encode(['passed' => true, 'cases' => $results, 'concurrent_overlap_observed' => true, 'test_row_counts_restored' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
