<?php

declare(strict_types=1);
use App\Support\PostgresCloud;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
try {
    $engine = $argv[1] ?? 'mysql';
    if ($engine === 'mysql') {
        $e = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
        if (($e['DB_DATABASE'] ?? '') !== 'nfcheck' || ($e['DB_HOST'] ?? '') !== '127.0.0.1' || (int) ($e['DB_PORT'] ?? 0) !== 3307) {
            throw new RuntimeException('Unexpected local schema.');
        }
        $d = new PDO('mysql:host=127.0.0.1;port=3307;dbname=nfcheck', $e['DB_USERNAME'], $e['DB_PASSWORD']);
        if ($d->query('SELECT VERSION()')->fetchColumn() !== '8.4.13') {
            throw new RuntimeException('Unexpected MySQL version.');
        }
        $process = new Process([$root.'/.tools/mysql/mysql-8.4.13-winx64/bin/mysqldump.exe', '--host=127.0.0.1', '--port=3307', '--user='.$e['DB_USERNAME'], '--no-data', '--skip-add-drop-table', '--skip-lock-tables', '--skip-triggers', '--no-tablespaces', '--skip-comments', 'nfcheck'], $root, ['MYSQL_PWD' => $e['DB_PASSWORD']]);
    } elseif ($engine === 'postgres') {
        $c = PostgresCloud::load($root);
        $t = json_decode(file_get_contents($root.'/.tools/runtime/postgres-test-target.json'), true, 512, JSON_THROW_ON_ERROR);
        $schema = $t['schema'] ?? '';
        if (! preg_match('/^nfcheck_probe_[a-f0-9]{16}$/D', $schema) || $t['expires_at'] < time() || $t['connection']['host'] !== $c['host']) {
            throw new RuntimeException('A current owned validation schema is required.');
        }
        $process = new Process([$root.'/.tools/postgres-client/17.11/pg_dump.exe', '--schema-only', '--no-owner', '--no-privileges', '--no-comments', '--quote-all-identifiers', '--strict-names', '--schema='.$schema], $root, ['PGHOST' => $c['host'], 'PGPORT' => '5432', 'PGDATABASE' => 'postgres', 'PGUSER' => $c['username'], 'PGPASSWORD' => $c['password'], 'PGSSLMODE' => 'verify-full', 'PGSSLROOTCERT' => $c['ca_file'], 'PGCONNECT_TIMEOUT' => '10']);
    } else {
        throw new RuntimeException('Choose mysql or postgres.');
    }
    $process->setTimeout(120);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Schema-only export failed.');
    }
    $sql = $process->getOutput();
    if ($engine === 'mysql') {
        $sql = preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $sql);
    } else {
        $sql = str_replace($schema, 'nfcheck_online', $sql);
        $sql = preg_replace('/^\\\\(?:un)?restrict[^\r\n]*\r?\n/m', '', $sql);
    }
    if (preg_match('/^\\s*(INSERT INTO|COPY .* FROM stdin)/mi', $sql)) {
        throw new RuntimeException('Reference exports must not contain rows.');
    }
    $directory = $root.'/database/schema';
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $file = $directory.'/'.($engine === 'mysql' ? 'mysql' : 'postgresql').'-schema.sql';
    file_put_contents($file, "-- Reference structure only. Install with Laravel migrations, not by importing this snapshot.\n".rtrim($sql)."\n");
    echo json_encode(['saved' => basename($file), 'contains_rows' => false]),PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['saved' => false, 'error_type' => get_class($e)]),PHP_EOL;
    exit(1);
}
