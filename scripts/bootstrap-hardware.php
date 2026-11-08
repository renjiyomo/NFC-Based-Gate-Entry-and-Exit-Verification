<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$secretPath = $root.'/.tools/secrets/database.json';
$secrets = json_decode(file_get_contents($secretPath), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $secrets['root'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $db->query('SELECT VERSION() version, @@datadir data_directory')->fetch(PDO::FETCH_ASSOC);
$expected = str_replace('\\', '/', realpath($root.'/.tools/mysql-data'));
if ($identity['version'] !== '8.4.13' || strcasecmp(rtrim(str_replace('\\', '/', $identity['data_directory']), '/'), $expected) !== 0) {
    throw new RuntimeException('Unrecognized hardware-test database instance.');
}
if (! isset($secrets['hardware'])) {
    if ($db->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='nfcheck_hardware'")->fetchColumn()) {
        throw new RuntimeException('An unrecognized hardware schema already exists; no changes made.');
    }
    $secrets['hardware'] = bin2hex(random_bytes(32));
    file_put_contents($secretPath, json_encode($secrets, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
}
$db->exec('CREATE DATABASE IF NOT EXISTS nfcheck_hardware CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec("CREATE USER IF NOT EXISTS 'nfcheck_hardware'@'127.0.0.1' IDENTIFIED BY ".$db->quote($secrets['hardware']));
$db->exec("GRANT ALL PRIVILEGES ON nfcheck_hardware.* TO 'nfcheck_migrator'@'127.0.0.1'");
$envPath = $root.'/.env.hardware';
$existing = is_file($envPath) ? Dotenv\Dotenv::parse(file_get_contents($envPath)) : [];
$values = ['APP_ENV' => 'local', 'APP_KEY' => ! empty($existing['APP_KEY']) ? $existing['APP_KEY'] : 'base64:'.base64_encode(random_bytes(32)), 'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost:8003', 'NFCHECK_UI_PREVIEW' => 'false', 'NFCHECK_NFC_SIMULATOR' => 'false', 'NFCHECK_HARDWARE_LAB' => 'true', 'DB_DATABASE' => 'nfcheck_hardware', 'DB_USERNAME' => 'nfcheck_hardware', 'DB_PASSWORD' => $secrets['hardware'], 'DB_URL' => 'null', 'SESSION_COOKIE' => 'nfcheck-hardware-session', 'SESSION_DRIVER' => 'database', 'SESSION_ENCRYPT' => 'true', 'MAIL_MAILER' => 'array', 'CACHE_PREFIX' => 'nfcheck_hardware_', 'CACHE_STORE' => 'file'];
$text = file_get_contents($root.'/.env.example');
foreach ($values as $key => $value) {
    $line = $key.'='.$value;
    $text = preg_match('/^'.preg_quote($key, '/').'=.*/m', $text) ? preg_replace_callback('/^'.preg_quote($key, '/').'=.*/m', fn () => $line, $text) : $text."\n".$line."\n";
}
file_put_contents($envPath, $text, LOCK_EX);
echo "Isolated hardware workspace prepared; main and simulator data preserved.\n";
