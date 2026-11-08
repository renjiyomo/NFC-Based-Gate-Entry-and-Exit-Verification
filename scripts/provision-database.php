<?php

declare(strict_types=1);
use Symfony\Component\Process\Process;

// CLI-only provisioning of the new isolated instance; never accepts arbitrary schemas/hosts.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$secretsPath = $root.'/.tools/secrets/database.json';
$generate = static fn (): string => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
if (! is_file($secretsPath)) {
    file_put_contents($secretsPath, json_encode([
        'root' => $generate(), 'migrator' => $generate(), 'app' => $generate(), 'test' => $generate(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
}
$secrets = json_decode(file_get_contents($secretsPath), true, 512, JSON_THROW_ON_ERROR);
$connect = static fn (): PDO => new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $secrets['root'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
    $db = $connect();
} catch (PDOException) {
    $log = file_get_contents($root.'/.tools/mysql-data/server.err');
    preg_match_all('/A temporary password is generated for root@localhost: (.+)/', $log, $matches);
    if (empty($matches[1])) {
        throw new RuntimeException('Initial root credential unavailable; existing data was not changed.');
    }
    $temporary = trim(end($matches[1]));
    $clientConfig = $root.'/.tools/secrets/initial-root.ini';
    file_put_contents($clientConfig, "[client]\nhost=127.0.0.1\nport=3307\nprotocol=TCP\nuser=root\npassword=\"".addcslashes($temporary, '\\"')."\"\n");
    try {
        $process = new Process([$root.'/.tools/mysql/mysql-8.4.13-winx64/bin/mysql.exe', '--defaults-extra-file='.$clientConfig, '--connect-expired-password']);
        $process->setInput("ALTER USER CURRENT_USER() IDENTIFIED BY '".$secrets['root']."';\n");
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Initial root password setup failed; details deliberately not echoed.');
        }
    } finally {
        if (is_file($clientConfig)) {
            unlink($clientConfig);
        }
    }
    $db = $connect();
}
$identity = $db->query('SELECT VERSION() version, @@datadir data_directory')->fetch(PDO::FETCH_ASSOC);
$version = $identity['version'];
$expectedData = str_replace('\\', '/', realpath($root.'/.tools/mysql-data'));
if ($version !== '8.4.13' || strcasecmp(rtrim(str_replace('\\', '/', $identity['data_directory']), '/'), $expectedData) !== 0) {
    throw new RuntimeException('Unexpected database server version; refusing provisioning.');
}
foreach (['nfcheck', 'nfcheck_testing'] as $schema) {
    $db->exec("CREATE DATABASE IF NOT EXISTS `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
foreach (['migrator' => 'nfcheck_migrator', 'app' => 'nfcheck_app', 'test' => 'nfcheck_test'] as $key => $user) {
    $db->exec("CREATE USER IF NOT EXISTS '$user'@'127.0.0.1' IDENTIFIED BY '".$secrets[$key]."'");
}
foreach (['nfcheck', 'nfcheck_testing'] as $schema) {
    $db->exec("GRANT ALL PRIVILEGES ON `$schema`.* TO 'nfcheck_migrator'@'127.0.0.1'");
}
foreach (['nfcheck' => 'nfcheck_app', 'nfcheck_testing' => 'nfcheck_test'] as $schema => $user) {
    // Per-table grants are applied after migration; no runtime DDL or cross-schema access.
    $db->exec("GRANT SELECT ON `$schema`.* TO '$user'@'127.0.0.1'");
}
$writeEnv = static function (string $path, array $values): void {
    $text = is_file($path) ? file_get_contents($path) : file_get_contents(dirname(__DIR__).'/.env.example');
    foreach ($values as $key => $value) {
        $line = $key.'='.$value;
        $text = preg_match('/^'.preg_quote($key, '/').'=.*/m', $text) ? preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $text) : $text."\n".$line."\n";
    }
    file_put_contents($path, $text);
};
$writeEnv($root.'/.env', ['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'nfcheck', 'DB_USERNAME' => 'nfcheck_app', 'DB_PASSWORD' => $secrets['app'], 'SESSION_DRIVER' => 'database', 'SESSION_ENCRYPT' => 'true', 'NFCHECK_UI_PREVIEW' => 'false', 'MAIL_MAILER' => 'smtp', 'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => '1025', 'MAIL_FROM_ADDRESS' => 'security@nfcheck.test']);
$writeEnv($root.'/.env.testing', ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'nfcheck_testing', 'DB_USERNAME' => 'nfcheck_test', 'DB_PASSWORD' => $secrets['test'], 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'NFCHECK_UI_PREVIEW' => 'false']);
$receipt = ['version' => $version, 'port' => 3307, 'bind' => '127.0.0.1', 'schemas' => ['nfcheck', 'nfcheck_testing'], 'signing_fingerprint' => 'BCA43417C3B485DD128EC6D4B7B3B788A8D3785C', 'archive_sha256' => 'e9cd98b1d23f122987b1afca26ad942efec5891481103d48ae3cde237fa3ea54'];
file_put_contents($root.'/.tools/mysql/provision-receipt.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Oracle MySQL $version provisioned with separate migration/runtime/test accounts. Credentials were not printed.\n";
