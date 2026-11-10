<?php

declare(strict_types=1);
use App\Support\PostgresCloud;
use App\Support\PostgresRecovery;
use App\Support\PostgresSchema;
use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
try {
    $c = PostgresCloud::load($root);
    $runtime = json_decode(file_get_contents($root.'/.tools/secrets/cloud-runtime.json'), true, 512, JSON_THROW_ON_ERROR);
    $fingerprint = hash('sha256', $c['host']."\0".$c['database']."\0".$c['username']."\0".hash_file('sha256', $c['ca_file']));
    if (! hash_equals($runtime['connection_fingerprint'], $fingerprint) || $runtime['schema'] !== 'nfcheck_online') {
        throw new RuntimeException('Saved online target does not match.');
    }
    $db = PostgresCloud::connect($c);
    $applied = $db->query('SELECT migration FROM nfcheck_online.migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = array_map(fn ($f) => basename($f, '.php'), glob($root.'/database/migrations/*.php'));
    $pending = array_values(array_diff($files, $applied));
    if (! in_array('--apply', $argv, true) || ! $pending) {
        echo json_encode(['mode' => 'check', 'current_migrations' => count($applied), 'pending' => $pending, 'ready_for_latest_code' => ! $pending], JSON_PRETTY_PRINT),PHP_EOL;
        exit;
    }
    $role = explode('.', $runtime['connection']['username'])[0];
    if (! preg_match('/^nfcheck_app_[a-f0-9]+$/D', $role)) {
        throw new RuntimeException('Unexpected runtime role.');
    }
    PostgresRecovery::drill($root, $c, 'nfcheck_online', $runtime['app_key']);
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    PostgresSchema::migrate($root, $c, 'nfcheck_online');
    $db = PostgresCloud::connect($c);
    PostgresSchema::grantRuntime($db, 'nfcheck_online', $role);
    echo json_encode(['upgraded' => true, 'applied_pending' => count($pending), 'runtime_grants_applied' => true, 'keys_and_passwords_preserved' => true]),PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['upgraded' => false, 'error_type' => get_class($e)]),PHP_EOL;
    exit(1);
}
