<?php

declare(strict_types=1);

use App\Enums\PersonType;
use App\Models\AccessEvent;
use App\Models\AnomalyFlag;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Models\SummaryResult;
use App\Models\SummaryTask;
use App\Models\User;
use App\Services\Anomalies\Detection;
use App\Services\Anomalies\Review;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\AutomaticPassages;
use App\Services\GateOperations\GateOperations;
use App\Services\GateOperations\MissingExits;
use App\Services\Monitoring\ReportFilters;
use App\Services\PersonManagement;
use App\Services\Summaries\Aggregates;
use App\Services\Summaries\Generation;
use App\Services\Summaries\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
// This harness never reads provider credentials or makes external requests.
Http::preventStrayRequests();
app()->bind(Provider::class, fn () => new class implements Provider
{
    public function configuration(): array
    {
        return ['ready' => true, 'provider' => 'test_fixture', 'model' => null];
    }

    public function generate(array $snapshot): array
    {
        return ['output' => ['fact_ids' => ['entries', 'exits', 'attempts', 'denied', 'flags'], 'limitation_ids' => array_keys(Aggregates::LIMITATIONS)]];
    }
});

if (($argv[1] ?? '') === '--worker') {
    try {
        $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        echo "READY\n";
        flush();
        $actor = User::findOrFail($input['actor']);
        if ($input['action'] === 'issue_label') {
            $person = Person::findOrFail($input['person']);
            $receipt = app(CredentialLifecycle::class)->issue($actor, ['person_id' => $person->id, 'record_version' => $person->record_version, 'mode' => 'new', 'request_id' => $input['request']], 'label-race', 'physical');
            echo json_encode(['label' => $receipt['credential']['label']], JSON_THROW_ON_ERROR);
            exit(0);
        }
        if ($input['action'] === 'missing_exit') {
            $created = DB::transaction(function () use ($input): bool {
                Person::whereKey($input['person'])->lockForUpdate()->firstOrFail();

                return (bool) app(MissingExits::class)->recordLocked(AccessEvent::findOrFail($input['entry']), now())?->wasRecentlyCreated;
            }, 3);
            echo json_encode(['created' => $created], JSON_THROW_ON_ERROR);
            exit(0);
        }
        if ($input['action'] === 'auto_scan') {
            if (isset($input['clock'])) {
                CarbonImmutable::setTestNow(CarbonImmutable::parse($input['clock']));
            }
            $r = app(AutomaticPassages::class)->automaticScan($actor, Gate::findOrFail($input['gate']), ['request_id' => $input['request'], 'payload' => $input['payload']], $input['session'], 'physical');
            echo json_encode(['id' => $r['id'], 'passage' => $r['passage']['id'] ?? null, 'duplicate' => $r['duplicate_of']['id'] ?? null], JSON_THROW_ON_ERROR);
            exit(0);
        }
        if ($input['action'] === 'detect') {
            echo json_encode(app(Detection::class)->run(new ReportFilters, 'physical', $actor), JSON_THROW_ON_ERROR);
            exit(0);
        }
        if ($input['action'] === 'anomaly_review') {
            try {
                app(Review::class)->append($actor, AnomalyFlag::findOrFail($input['flag']), 'physical', ['expected_revision' => 0, 'status' => 'in_review', 'reason' => 'Synthetic concurrent review']);
                echo json_encode(['saved' => true]);
            } catch (ValidationException $error) {
                if (! isset($error->errors()['expected_revision'])) {
                    throw $error;
                }
                echo json_encode(['saved' => false]);
            }
            exit(0);
        }
        if ($input['action'] === 'summary') {
            config(['ai_summaries.max_requests_per_day' => $input['limit']]);
            echo json_encode(['processed' => app(Generation::class)->process(SummaryTask::findOrFail($input['task']))], JSON_THROW_ON_ERROR);
            exit(0);
        }
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
$tables = ['users', 'persons', 'gates', 'guard_gate_assignments', 'access_authorizations', 'student_profiles', 'nfc_credentials', 'credential_assignments', 'scan_attempts', 'access_events', 'audit_logs', 'anomaly_flags', 'anomaly_evidence', 'anomaly_reviews', 'summary_requests', 'summary_tasks', 'summary_results', 'summary_reviews', 'summary_controls'];
$counts = static fn (): array => array_combine($tables, array_map(static fn (string $table): int => (int) $maintenance->query('SELECT COUNT(*) FROM nfcheck_testing.'.$table)->fetchColumn(), $tables));
$before = $counts();
$quotaBefore = DB::table('summary_controls')->where('id', 1)->first();
$counterBefore = DB::table('credential_counters')->get()->keyBy('kind');
foreach (['scan_attempts', 'anomaly_flags', 'summary_requests'] as $table) {
    if ($before[$table] !== 0) {
        throw new RuntimeException('Concurrency fixtures require empty testing history; existing rows were left unchanged.');
    }
}
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
$run = static function (array $inputs, string $lockTable, int|string $lockId) use ($root, $maintenance, &$children, $check): array {
    // Measure overlapping transactions, without worker startup time consuming
    // the ten-second business window on a slow development machine.
    $clock = now()->toISOString();
    $inputs = array_map(fn ($input) => [...$input, 'clock' => $clock], $inputs);
    // The parent uses its testing-only maintenance connection to hold barriers,
    // including immutable tables; worker runtime grants remain unchanged.
    $maintenance->beginTransaction();
    $lockColumn = $lockTable === 'credential_counters' ? 'kind' : 'id';
    $barrier = $maintenance->prepare('SELECT '.$lockColumn.' FROM nfcheck_testing.'.$lockTable.' WHERE '.$lockColumn.' = ? FOR UPDATE');
    $barrier->execute([$lockId]);
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
        $check($waiters >= 2, 'Could not establish two concurrent blocked transactions: '.implode('/', array_column($inputs, 'action')).' on '.$lockTable.' ('.$waiters.' waiters).');
    } finally {
        $maintenance->rollBack();
    }
    $outputs = [];
    foreach ($workers as $worker) {
        $worker->wait();
        $decoded = json_decode(trim(str_replace("READY\n", '', str_replace("\r", '', $worker->getOutput()))), true, 512, JSON_THROW_ON_ERROR);
        $check($worker->isSuccessful(), 'Concurrency worker failed: '.($decoded['error'] ?? 'unknown').'; no input was printed.');
        $outputs[] = $decoded;
    }

    return $outputs;
};

try {
    $suffix = (string) Str::uuid();
    foreach (['admin', 'guard', 'guard', 'admin'] as $index => $role) {
        $user = User::create(['name' => 'Synthetic concurrency '.$role, 'email' => 'race-'.$suffix.'-'.$index.'@example.invalid', 'password' => bin2hex(random_bytes(24)), 'role' => $role]);
        $ownedUsers[] = $user->id;
    }
    [$admin, $guard1, $guard2, $admin2] = array_map(fn ($id) => User::findOrFail($id), $ownedUsers);
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

    [$person, $receipt] = $fixture();
    $exit = app(GateOperations::class)->scan($guard1, $gate, ['request_id' => (string) Str::uuid(), 'payload' => $receipt['payload'], 'direction' => 'exit'], 'first-exit', 'physical');
    $exitAttempt = ScanAttempt::where('public_id', $exit['id'])->sole();
    app(GateOperations::class)->review($guard1, $gate, $exitAttempt, ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'], 'first-exit', 'physical');
    $firstPerson = ScanAttempt::whereNotNull('person_id')->orderBy('received_at')->orderBy('id')->value('person_id');
    $output = $run([['actor' => $admin->id, 'action' => 'detect'], ['actor' => $admin2->id, 'action' => 'detect']], 'persons', $firstPerson);
    $check(AnomalyFlag::where('anchor_attempt_id', $exitAttempt->id)->count() === 1 && array_sum(array_column($output, 'created')) === AnomalyFlag::count(), 'Concurrent detection did not preserve unique flags.');
    $results[] = 'different administrators / overlapping detection: one flag per detection key';
    $flag = AnomalyFlag::where('anchor_attempt_id', $exitAttempt->id)->sole();
    $output = $run([['actor' => $admin->id, 'action' => 'anomaly_review', 'flag' => $flag->id], ['actor' => $admin2->id, 'action' => 'anomaly_review', 'flag' => $flag->id]], 'scan_attempts', $exitAttempt->id);
    $check(array_sum(array_column($output, 'saved')) === 1 && $flag->reviews()->count() === 1, 'Concurrent anomaly review accepted a stale revision.');
    $results[] = 'different administrators / same anomaly revision: one review and one stale rejection';

    DB::table('summary_controls')->where('id', 1)->update(['quota_day' => now('UTC')->toDateString(), 'claims' => 0]);
    $generation = app(Generation::class);
    $request = $generation->request($admin, new ReportFilters, 'physical', (string) Str::uuid());
    $job = ['actor' => $admin->id, 'action' => 'summary', 'task' => $request->task->id, 'limit' => 3];
    $output = $run([$job, $job], 'users', $admin->id);
    $check(array_sum(array_column($output, 'processed')) === 1 && $request->result()->sole()->status === 'ready' && (int) DB::table('summary_controls')->value('claims') === 1, 'One summary task was claimed more than once.');
    $results[] = 'same summary task / two workers: one claim and one validated fixture result';

    DB::table('summary_controls')->where('id', 1)->update(['claims' => 0]);
    $one = $generation->request($admin, new ReportFilters, 'physical', (string) Str::uuid());
    $two = $generation->request($admin2, new ReportFilters, 'physical', (string) Str::uuid());
    $output = $run([['actor' => $admin->id, 'action' => 'summary', 'task' => $one->task->id, 'limit' => 1], ['actor' => $admin2->id, 'action' => 'summary', 'task' => $two->task->id, 'limit' => 1]], 'summary_controls', 1);
    $check(array_sum(array_column($output, 'processed')) === 1 && (int) DB::table('summary_controls')->value('claims') === 1 && SummaryResult::whereIn('summary_request_id', [$one->id, $two->id])->where('status', 'ready')->count() === 1 && SummaryResult::whereIn('summary_request_id', [$one->id, $two->id])->where('error_code', 'daily_limit')->count() === 1, 'Concurrent summary jobs exceeded the quota.');
    $results[] = 'different summary tasks / one remaining claim: one result and one daily-limit denial';
    // Current gate-account contract: no grant, schedule or confirmation request.
    $guard1->forceFill(['gate_id' => $gate->id])->save();
    [$person,$receipt] = $fixture();
    $one = ['actor' => $guard1->id, 'gate' => $gate->id, 'action' => 'auto_scan', 'session' => 'automatic-race', 'request' => (string) Str::uuid(), 'payload' => $receipt['payload']];
    $output = $run([$one, [...$one, 'request' => (string) Str::uuid()]], 'users', $guard1->id);
    $check(AccessEvent::where('person_id', $person->id)->count() === 1 && count(array_filter($output, fn ($r) => $r['duplicate'] !== null)) === 1, 'Automatic concurrent scans created duplicate passages.');
    $results[] = 'gate account / concurrent different scan IDs: one automatic passage and one duplicate';
    [$person,$receipt] = $fixture();
    $one = [...$one, 'request' => (string) Str::uuid(), 'payload' => $receipt['payload']];
    $output = $run([$one, $one], 'users', $guard1->id);
    $check(AccessEvent::where('person_id', $person->id)->count() === 1 && ScanAttempt::where('person_id', $person->id)->count() === 1 && $output[0]['passage'] === $output[1]['passage'], 'Automatic same-request retry was not idempotent.');
    $results[] = 'gate account / concurrent same scan ID: one attempt and one automatic passage';

    [$person,$receipt] = $fixture();
    app(AutomaticPassages::class)->automaticScan($guard1, $gate, ['request_id' => (string) Str::uuid(), 'payload' => $receipt['payload']], 'rapid-race', 'physical');
    $one = [...$one, 'request' => (string) Str::uuid(), 'payload' => $receipt['payload']];
    $run([$one, [...$one, 'request' => (string) Str::uuid()]], 'users', $guard1->id);
    $flag = AnomalyFlag::where('rule_code', 'rapid_scans')->whereHas('anchor', fn ($q) => $q->where('person_id', $person->id))->sole();
    $check(ScanAttempt::where('person_id', $person->id)->count() === 3 && AccessEvent::where('person_id', $person->id)->count() === 1 && $flag->evidence()->count() === 3, 'Concurrent scans did not produce exactly one repeated-scan flag with three witnesses.');
    $results[] = 'automatic activity / concurrent scans: one repeated-scan flag, three attempts and one passage';

    [$person, $receipt, $credential] = $fixture();
    $one = [...$one, 'request' => (string) Str::uuid(), 'payload' => $receipt['payload']];
    $run([$one, ['actor' => $admin->id, 'gate' => $gate->id, 'credential' => $credential->id, 'action' => 'revoke']], 'persons', $person->id);
    $check($credential->fresh()->status === 'revoked' && AccessEvent::where('person_id', $person->id)->count() <= 1, 'Automatic scan/lost revocation race failed.');
    $event = AccessEvent::where('person_id', $person->id)->first();
    if ($event) {
        $passageAudit = DB::table('audit_logs')->where('action', 'passage.recorded')->where('target_id', $event->id)->value('id');
        $revocationAudit = DB::table('audit_logs')->where('action', 'credential.revoked')->where('target_id', $credential->id)->value('id');
        $check($passageAudit && $revocationAudit && $passageAudit < $revocationAudit, 'Automatic passage was recorded after lost revocation.');
    }
    $after = app(AutomaticPassages::class)->scan($guard1, $gate, ['request_id' => (string) Str::uuid(), 'payload' => $receipt['payload'], 'direction' => 'entry'], 'after-lost-revocation', 'physical');
    $check($after['verification']['reason'] === 'revoked' && $after['identity'] === null && $after['passage'] === null, 'Lost coin was accepted after revocation completed.');
    $results[] = 'automatic scan versus lost revocation: no passage after committed revocation; later scans denied';
    $secondGate = Gate::create(['code' => 'RACE-SECOND-'.Str::random(8), 'name' => 'Synthetic second race gate', 'status' => 'active']);
    $guard2->forceFill(['gate_id' => $secondGate->id])->save();
    [$person,$receipt] = $fixture();
    $one = ['actor' => $guard1->id, 'gate' => $gate->id, 'action' => 'auto_scan', 'session' => 'state-cross-gate', 'request' => (string) Str::uuid(), 'payload' => $receipt['payload']];
    $two = [...$one, 'actor' => $guard2->id, 'gate' => $secondGate->id, 'request' => (string) Str::uuid()];
    $output = $run([$one, $two], 'persons', $person->id);
    $check(AccessEvent::where('person_id', $person->id)->count() === 1 && count(array_filter($output, fn ($r) => $r['duplicate'] !== null)) === 1, 'Different gates did not serialize automatic state.');
    $results[] = 'automatic direction / different gates: one Entry and one suppressed repeat under person lock';
    CarbonImmutable::setTestNow(now()->subDays(2));
    [$person,$receipt] = $fixture();
    app(AutomaticPassages::class)->automaticScan($guard1, $gate, ['request_id' => (string) Str::uuid(), 'payload' => $receipt['payload']], 'overdue-entry', 'physical');
    CarbonImmutable::setTestNow();
    $entry = AccessEvent::where('person_id', $person->id)->sole();
    $job = ['actor' => $admin->id, 'action' => 'missing_exit', 'person' => $person->id, 'entry' => $entry->id];
    $output = $run([$job, $job], 'persons', $person->id);
    $check(array_sum(array_column($output, 'created')) === 1 && AnomalyFlag::where('anchor_attempt_id', $entry->scan_attempt_id)->where('rule_code', 'missing_exit')->count() === 1, 'Concurrent cutoff jobs created duplicate Missing Exit flags.');
    $results[] = 'concurrent Missing Exit jobs: one append-only flag and one idempotent existing result';
    $labelJobs = [];
    foreach ([$admin, $admin2] as $issuer) {
        [$labelPerson,,$labelCredential] = $fixture();
        app(CredentialLifecycle::class)->terminate($admin, $labelCredential->fresh(), 'revoke', 'damaged');
        $labelJobs[] = ['actor' => $issuer->id, 'action' => 'issue_label', 'person' => $labelPerson->id, 'request' => (string) Str::uuid()];
    }
    $labelResults = $run($labelJobs, 'credential_counters', 'student');
    $check(count(array_unique(array_column($labelResults, 'label'))) === 2, 'Concurrent issuers allocated the same label.');
    $results[] = 'different administrators / simultaneous issuance: unique sequential labels';
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
        $summaryIds = DB::table('summary_requests')->whereIn('actor_id', $ownedUsers)->pluck('id')->all();
        $flagIds = DB::table('anomaly_flags')->whereIn('anchor_attempt_id', DB::table('scan_attempts')->whereIn('guard_id', $ownedUsers)->select('id'))->pluck('id')->all();
        $execute("DELETE FROM nfcheck_testing.audit_logs WHERE target_type = 'App\\\\Models\\\\AnomalyFlag' AND target_id IN (:ids)", $flagIds);
        foreach (['summary_reviews', 'summary_results', 'summary_tasks'] as $table) {
            $execute('DELETE FROM nfcheck_testing.'.$table.' WHERE summary_request_id IN (:ids)', $summaryIds);
        }
        $execute("DELETE FROM nfcheck_testing.audit_logs WHERE target_type = 'App\\\\Models\\\\SummaryRequest' AND target_id IN (:ids)", $summaryIds);
        $execute('DELETE FROM nfcheck_testing.summary_requests WHERE id IN (:ids)', $summaryIds);
        foreach (['anomaly_reviews', 'anomaly_evidence'] as $table) {
            $execute('DELETE FROM nfcheck_testing.'.$table.' WHERE anomaly_flag_id IN (:ids)', $flagIds);
        }
        $execute('DELETE FROM nfcheck_testing.anomaly_flags WHERE id IN (:ids)', $flagIds);
        $restoreQuota = $maintenance->prepare('UPDATE nfcheck_testing.summary_controls SET quota_day = ?, claims = ? WHERE id = 1');
        $restoreQuota->execute([$quotaBefore->quota_day, $quotaBefore->claims]);
        $restoreCounter = $maintenance->prepare('UPDATE nfcheck_testing.credential_counters SET last_number=? WHERE kind=?');
        foreach ($counterBefore as $counter) {
            $restoreCounter->execute([$counter->last_number, $counter->kind]);
        }
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
        $execute('UPDATE nfcheck_testing.users SET gate_id = NULL WHERE id IN (:ids)', $ownedUsers);
        $execute('DELETE FROM nfcheck_testing.gates WHERE id IN (:ids)', array_values(array_filter([$ownedGate, $secondGate->id ?? null])));
        $execute('DELETE FROM nfcheck_testing.audit_logs WHERE actor_id IN (:ids)', $ownedUsers);
        $execute('DELETE FROM nfcheck_testing.users WHERE id IN (:ids)', $ownedUsers);
        $maintenance->commit();
    }
}
$check($before === $counts(), 'Testing row counts were not restored after scoped cleanup.');
$quotaAfter = DB::table('summary_controls')->where('id', 1)->first();
$check($quotaBefore == $quotaAfter, 'Testing quota was not restored.');
echo json_encode(['passed' => true, 'cases' => $results, 'concurrent_overlap_observed' => true, 'test_row_counts_restored' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
