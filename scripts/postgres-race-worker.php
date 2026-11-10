<?php

declare(strict_types=1);
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\User;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\AutomaticPassages;
use App\Support\PostgresCloud;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$result = ['status' => 'error'];
try {
    $target = json_decode(file_get_contents($root.'/.tools/runtime/postgres-test-target.json'), true, 512, JSON_THROW_ON_ERROR);
    if (! hash_equals($target['run_token'], (string) getenv('NFCHECK_PG_TEST_RUN')) || $target['expires_at'] < time() || ! preg_match('/^nfcheck_probe_[a-f0-9]{16}$/D', $target['schema'])) {
        throw new RuntimeException('Invalid owned race target.');
    }
    PostgresCloud::configure($target['connection'], $target['schema']);
    config(['app.key' => $target['app_key']]);
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    file_put_contents($input['started'], (string) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid);
    $f = $input['fixture'];
    $result = DB::transaction(function () use ($input, $f): array {
        $kind = $input['kind'];
        $actor = User::findOrFail($kind === 'revoke' ? $f['admin_id'] : $f['guard_id']);
        if ($input['hold']) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Person::whereKey($f['person_id'])->lockForUpdate()->firstOrFail();
            file_put_contents($input['held'], 'held');
            $deadline = microtime(true) + 25;
            while (! is_file($input['release'])) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Race release deadline exceeded.');
                }
                usleep(10000);
            }
        }
        if ($kind === 'revoke') {
            app(CredentialLifecycle::class)->terminate($actor, NfcCredential::findOrFail($f['credential_id']), 'revoke', 'lost');

            return ['status' => 'revoked'];
        }
        $r = app(AutomaticPassages::class)->scan($actor, Gate::findOrFail($f['gate_id']), ['request_id' => $input['request_id'], 'payload' => $f['payload'], 'direction' => 'entry'], 'race-session', 'physical');

        return ['status' => 'scanned', 'passage' => $r['passage'] !== null, 'outcome' => $r['verification']['outcome'], 'duplicate' => $r['review']['reason'] === 'duplicate_passage'];
    }, 3);
} catch (Throwable $error) {
    $result = ['status' => 'error', 'type' => get_class($error), 'sql_state' => $error instanceof QueryException ? ($error->errorInfo[0] ?? null) : null];
}
echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
exit($result['status'] === 'error' ? 1 : 0);
