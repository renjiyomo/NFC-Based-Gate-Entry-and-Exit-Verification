<?php

declare(strict_types=1);
use App\Support\CloudServices;
use App\Support\PostgresCloud;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;

// Read-only connection check. Never migrates, seeds, grants or uploads files.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$result = ['database_engine' => 'postgresql', 'database_tls' => false, 'storage_bucket_access' => false, 'service_checks_passed' => false];
try {
    $maintenance = PostgresCloud::load($root);
    $runtimePath = $root.'/.tools/secrets/cloud-runtime.json';
    if (! is_file($runtimePath)) {
        throw new RuntimeException('Initialize the approved online schema before checking its runtime account.');
    }
    $runtime = json_decode(file_get_contents($runtimePath), true, 512, JSON_THROW_ON_ERROR);
    if ($runtime['connection']['host'] !== $maintenance['host'] || $runtime['schema'] !== 'nfcheck_online') {
        throw new RuntimeException('The runtime handoff belongs to a different target.');
    }
    $database = PostgresCloud::connect($runtime['connection']);
    $result['database_tls'] = true;
    $result['database_version'] = $database->query('SHOW server_version')->fetchColumn();
    $result['restricted_runtime_account'] = str_starts_with($database->query('SELECT current_user')->fetchColumn(), 'nfcheck_app_');
    $result['history_update_denied'] = ! $database->query("SELECT has_table_privilege(current_user, 'nfcheck_online.access_events', 'UPDATE')")->fetchColumn();
    $result['review_update_allowed'] = (bool) $database->query("SELECT has_column_privilege(current_user, 'nfcheck_online.scan_attempts', 'review_status', 'UPDATE')")->fetchColumn();
    $result['fact_update_denied'] = ! $database->query("SELECT has_column_privilege(current_user, 'nfcheck_online.scan_attempts', 'reason', 'UPDATE')")->fetchColumn();
    $s = CloudServices::loadStorage($root);
    $storage = new S3Client(['version' => 'latest', 'region' => $s['region'], 'endpoint' => $s['endpoint'], 'use_path_style_endpoint' => true, 'credentials' => ['key' => $s['access_key_id'], 'secret' => $s['secret_access_key']], 'retries' => 0, 'http' => ['connect_timeout' => 3, 'timeout' => 12, 'verify' => $root.'/.tools/php/cacert.pem']]);
    $storage->headBucket(['Bucket' => $s['bucket']]);
    $result['storage_bucket_access'] = true;
    $result['service_checks_passed'] = $result['database_tls'] && $result['restricted_runtime_account'] && $result['history_update_denied'] && $result['review_update_allowed'] && $result['fact_update_denied'];
    $result['scope'] = 'Read-only service and permission checks. Container build, hosted HTTPS and physical NFC acceptance remain separate.';
} catch (Throwable $exception) {
    // Provider exception strings can contain connection details and signed URLs.
    $result['error_type'] = get_class($exception);
    if ($exception instanceof PDOException) {
        $result['database_error_code'] = $exception->errorInfo[1] ?? null;
    }
    if ($exception instanceof AwsException) {
        $result['storage_http_status'] = $exception->getStatusCode();
    }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
exit($result['service_checks_passed'] ? 0 : 1);
