<?php

declare(strict_types=1);

use App\Support\RecoveryArchive;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli' || getenv('NFCHECK_BACKUP_PREPARED') !== '1') {
    fwrite(STDERR, 'Run the ACL-protected PowerShell backup wrapper.'.PHP_EOL);
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$workspace = $argv[1] ?? '';
$sources = ['main' => ['nfcheck', '.env'], 'simulator' => ['nfcheck_simulator', '.env.simulator'], 'hardware' => ['nfcheck_hardware', '.env.hardware']];
if (! isset($sources[$workspace]) || count($argv) > 3 || (isset($argv[2]) && $argv[2] !== '--verify-restore')) {
    exit(1);
}
[$schema, $environment] = $sources[$workspace];
$run = gmdate('Ymd-His').'-'.bin2hex(random_bytes(8));
$temporary = $root.'/.tools/backups/work-'.$run;
$destination = $root.'/.tools/backups/'.$workspace.'-'.$run.'.zip';
$createdSchema = null;
$db = null;
$evidence = ['workspace' => $workspace, 'started_utc' => gmdate('c'), 'archive' => basename($destination), 'restored' => false];
$started = microtime(true);
$stage = 'connection';
$validIdentifier = static function (string $value): string {
    if (! preg_match('/^[a-z0-9_]+$/D', $value)) {
        throw new RuntimeException('Unexpected database identifier.');
    }

    return '`'.$value.'`';
};
$fingerprints = static function (PDO $db, string $schema) use ($validIdentifier): array {
    $result = [];
    $tableQuery = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME');
    $tableQuery->execute([$schema]);
    foreach ($tableQuery->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $primary = $db->prepare("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX");
        $primary->execute([$schema, $table]);
        $columns = $primary->fetchAll(PDO::FETCH_COLUMN);
        if (! $columns) {
            throw new RuntimeException('Backup verification requires deterministic primary keys.');
        }
        $query = $db->query('SELECT * FROM '.$validIdentifier($schema).'.'.$validIdentifier($table).' ORDER BY '.implode(',', array_map($validIdentifier, $columns)));
        $hash = hash_init('sha256');
        $count = 0;
        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $count++;
            foreach ($row as $value) {
                hash_update($hash, $value === null ? 'N;' : strlen((string) $value).':'.$value.';');
            }
        }
        $result[$table] = ['rows' => $count, 'sha256' => hash_final($hash)];
    }

    return $result;
};
try {
    $secrets = json_decode(file_get_contents($root.'/.tools/secrets/database.json'), true, 512, JSON_THROW_ON_ERROR);
    $db = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $secrets['root'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $db->query('SELECT VERSION() version, @@datadir directory')->fetch(PDO::FETCH_ASSOC);
    if ($identity['version'] !== '8.4.13' || strcasecmp(rtrim(str_replace('\\', '/', $identity['directory']), '/'), str_replace('\\', '/', realpath($root.'/.tools/mysql-data'))) !== 0) {
        throw new RuntimeException('Backup requires the verified private MySQL instance.');
    }
    $environmentBytes = file_get_contents($root.'/'.$environment);
    $environmentHash = hash('sha256', $environmentBytes);
    $settings = Dotenv\Dotenv::parse($environmentBytes);
    if ($settings['DB_DATABASE'] !== $schema || $settings['DB_HOST'] !== '127.0.0.1' || $settings['DB_PORT'] !== '3307') {
        throw new RuntimeException('Workspace database configuration does not match.');
    }
    $engines = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND ENGINE <> 'InnoDB'");
    $engines->execute([$schema]);
    if ((int) $engines->fetchColumn() !== 0) {
        throw new RuntimeException('Single-transaction backup requires InnoDB tables.');
    }
    mkdir($temporary, 0700);
    $optionFile = $temporary.'/client.ini';
    file_put_contents($optionFile, "[client]\nhost=127.0.0.1\nport=3307\nprotocol=TCP\nuser=root\npassword=\"".addcslashes($secrets['root'], '\\"')."\"\n");
    $keyFile = $root.'/.tools/secrets/backup-key.txt';
    if (! is_file($keyFile)) {
        $handle = fopen($keyFile, 'x');
        if ($handle === false) {
            throw new RuntimeException('Cannot create a new recovery key.');
        }
        fwrite($handle, base64_encode(random_bytes(32)));
        fclose($handle);
    }
    $key = trim(file_get_contents($keyFile));
    if (! preg_match('#^[A-Za-z0-9+/]{43}=$#D', $key)) {
        throw new RuntimeException('Unexpected recovery key format.');
    }
    $stage = 'source_snapshot';
    $before = $fingerprints($db, $schema);
    $stage = 'database_dump';
    $dump = new Process([$root.'/.tools/mysql/mysql-8.4.13-winx64/bin/mysqldump.exe', '--defaults-file='.$optionFile, '--no-login-paths', '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--hex-blob', '--skip-comments', '--result-file='.$temporary.'/database.sql', $schema], $root);
    $dump->setTimeout(120);
    $dump->run();
    if (! $dump->isSuccessful()) {
        throw new RuntimeException('Database backup client failed; output withheld.');
    }
    $files = ['database.sql' => $temporary.'/database.sql', 'environment.env' => $root.'/'.$environment];
    // Only approved normalized photo paths, never arbitrary files named in DB data.
    $photos = $db->query('SELECT DISTINCT photo_path FROM '.$validIdentifier($schema).'.persons WHERE photo_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($photos as $photo) {
        if (! preg_match('#^person-photos/[a-f0-9-]{36}\.jpg$#D', $photo)) {
            throw new RuntimeException('Unexpected private photo path.');
        }
        $files['private/'.$photo] = $root.'/storage/app/private/'.$photo;
    }
    $stage = 'encrypted_archive';
    $archive = new RecoveryArchive;
    $archive->create($destination, $files, $key, ['workspace' => $workspace, 'schema' => $schema, 'created_utc' => gmdate('c'), 'mysql' => '8.4.13']);
    $restored = $archive->read($destination, $key);
    if (! hash_equals($environmentHash, hash('sha256', $restored['files']['environment.env'])) || ! hash_equals($environmentHash, hash_file('sha256', $root.'/'.$environment))) {
        throw new RuntimeException('Workspace environment changed during backup.');
    }
    $evidence['environment_key_verified'] = true;
    $evidence['encrypted_entries_verified'] = count($restored['files']) + 1;
    $evidence['private_photos'] = count($photos);
    $evidence['archive_sha256'] = hash_file('sha256', $destination);
    if (($argv[2] ?? '') === '--verify-restore') {
        $sql = $restored['files']['database.sql'];
        $stage = 'isolated_restore';
        if (preg_match('/^(?:(?:CREATE|DROP)\s+DATABASE\b|USE\s+[`a-z])/im', $sql)) {
            throw new RuntimeException('Backup unexpectedly selects a schema.');
        }
        $target = 'nfcheck_restore_'.bin2hex(random_bytes(12));
        $db->exec('CREATE DATABASE '.$validIdentifier($target).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $createdSchema = $target; // Assigned only after CREATE succeeds; no existing schema can be replaced.
        $restore = new Process([$root.'/.tools/mysql/mysql-8.4.13-winx64/bin/mysql.exe', '--defaults-file='.$optionFile, '--no-login-paths', '--binary-mode', '--local-infile=0', '--database='.$target], $root);
        $restore->setInput($sql);
        $restore->setTimeout(120);
        $restore->run();
        if (! $restore->isSuccessful()) {
            throw new RuntimeException('Isolated restore client failed; output withheld.');
        }
        $stage = 'restore_comparison';
        $after = $fingerprints($db, $schema);
        $copy = $fingerprints($db, $target);
        if ($before !== $after || $before !== $copy) {
            throw new RuntimeException('Source changed during backup or restored rows differ. Repeat during a quiet interval.');
        }
        $evidence['restored'] = true;
        $evidence['tables_verified'] = count($copy);
        $evidence['rows_verified'] = array_sum(array_column($copy, 'rows'));
        $evidence['source_unchanged'] = true;
        $evidence['restore_schema'] = $target;
    }
    $evidence['passed'] = true;
} catch (Throwable $error) {
    $evidence['passed'] = false;
    // Never print SQL, option files, credentials or exception arguments/messages.
    $evidence['error_class'] = $error::class;
    $evidence['failed_stage'] = $stage;
} finally {
    if ($createdSchema !== null) {
        if (! preg_match('/^nfcheck_restore_[a-f0-9]{24}$/D', $createdSchema)) {
            throw new RuntimeException('Refusing unexpected restore cleanup target.');
        }
        $db->exec('DROP DATABASE '.$validIdentifier($createdSchema));
        $evidence['owned_restore_schema_removed'] = true;
    }
    // Captured filenames only; no recursive deletion or arbitrary extraction.
    foreach (['client.ini', 'database.sql'] as $name) {
        if (is_file($temporary.'/'.$name)) {
            unlink($temporary.'/'.$name);
        }
    }
    if (is_dir($temporary)) {
        rmdir($temporary);
    }
    $evidence['temporary_plaintext_removed'] = ! is_dir($temporary);
}
$evidence['elapsed_seconds'] = round(microtime(true) - $started, 3);
file_put_contents($root.'/.tools/backups/'.$workspace.'-'.$run.'.evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($evidence['passed'] ? 0 : 1);
