<?php

declare(strict_types=1);
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$secrets = json_decode(file_get_contents($root.'/.tools/secrets/database.json'), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $secrets['root'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $db->query('SELECT VERSION() version, @@datadir data_directory')->fetch(PDO::FETCH_ASSOC);
$expected = str_replace('\\', '/', realpath($root.'/.tools/mysql-data'));
if ($identity['version'] !== '8.4.13' || strcasecmp(rtrim(str_replace('\\', '/', $identity['data_directory']), '/'), $expected) !== 0) {
    throw new RuntimeException('Refusing migrations on an unrecognized database instance.');
}
$testingPath = $root.'/.env.testing';
$testing = Dotenv\Dotenv::parse(file_get_contents($testingPath));
if (empty($testing['APP_KEY'])) {
    file_put_contents($testingPath, preg_replace('/^APP_KEY=.*/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), file_get_contents($testingPath)));
}
$schemas = ['nfcheck' => 'local', 'nfcheck_testing' => 'testing'];
if (isset($secrets['simulator'])) {
    $schemas['nfcheck_simulator'] = 'local';
}
if (isset($secrets['hardware'])) {
    $schemas['nfcheck_hardware'] = 'local';
}
foreach ($schemas as $schema => $environment) {
    $process = new Process([PHP_BINARY, $root.'/artisan', 'migrate', '--no-interaction'], $root, [
        'APP_ENV' => $environment, 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
        'DB_DATABASE' => $schema, 'DB_USERNAME' => 'nfcheck_migrator', 'DB_PASSWORD' => $secrets['migrator'],
        'DB_URL' => false, 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file',
    ]);
    $process->setTimeout(120);
    $process->run(static function (string $type, string $buffer): void {
        echo $buffer;
    });
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Scoped migrations failed.');
    }
    $user = match ($schema) {
        'nfcheck' => 'nfcheck_app', 'nfcheck_testing' => 'nfcheck_test', 'nfcheck_simulator' => 'nfcheck_simulator', 'nfcheck_hardware' => 'nfcheck_hardware'
    };
    $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$schema'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (! preg_match('/^[a-z_]+$/D', $table)) {
            throw new RuntimeException('Unexpected table identifier.');
        }
        $permissions = match ($table) {
            'audit_logs', 'scan_attempts', 'access_events', 'passage_corrections', 'anomaly_flags', 'anomaly_evidence', 'anomaly_reviews', 'summary_requests', 'summary_results', 'summary_reviews', 'summary_tasks' => 'SELECT, INSERT', 'credential_counters', 'summary_controls', 'migrations', 'campus_programs', 'campus_departments' => 'SELECT', default => 'SELECT, INSERT, UPDATE, DELETE'
        };
        $db->exec("GRANT $permissions ON `$schema`.`$table` TO '$user'@'127.0.0.1'");
        if (in_array($table, ['campus_programs', 'campus_departments'], true)) {
            $privileges = $db->prepare("SELECT PRIVILEGE_TYPE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE = ? AND TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PRIVILEGE_TYPE IN ('INSERT','UPDATE','DELETE')");
            $privileges->execute(["'$user'@'127.0.0.1'", $schema, $table]);
            $remove = $privileges->fetchAll(PDO::FETCH_COLUMN);
            if ($remove) {
                $db->exec('REVOKE '.implode(', ', $remove)." ON `$schema`.`$table` FROM '$user'@'127.0.0.1'");
            }
        }
        if ($table === 'scan_attempts') {
            $db->exec("GRANT UPDATE (review_status, reviewed_at, reviewed_by, review_reason, duplicate_of_event_id, updated_at) ON `$schema`.`scan_attempts` TO '$user'@'127.0.0.1'");
        }
        if ($table === 'summary_tasks') {
            $db->exec("GRANT UPDATE (claimed_at) ON `$schema`.`summary_tasks` TO '$user'@'127.0.0.1'");
        }
        if ($table === 'summary_controls') {
            $db->exec("GRANT UPDATE (quota_day, claims) ON `$schema`.`summary_controls` TO '$user'@'127.0.0.1'");
        }
        if ($table === 'credential_counters') {
            $db->exec("GRANT UPDATE (last_number) ON `$schema`.`credential_counters` TO '$user'@'127.0.0.1'");
        }
    }
    echo "Scoped runtime privileges applied to $schema.\n";
}
