<?php

namespace App\Support;

use PDO;
use Symfony\Component\Process\Process;

final class PostgresRecovery
{
    public static function remapDump(string $sql, string $source, string $target): string
    {
        if (! preg_match('/^nfcheck_(online|probe_[a-f0-9]{16})$/D', $source) || ! preg_match('/^nfcheck_probe_[a-f0-9]{16}$/D', $target)) {
            throw new \RuntimeException('Restore targets are restricted to owned probes.');
        }
        $copy = false;
        $output = [];
        foreach (preg_split('/\r?\n/', $sql) as $line) {
            if (! $copy) {
                $line = str_replace('"'.$source.'"', '"'.$target.'"', $line);
            }
            $output[] = $line;
            if (! $copy && str_starts_with($line, 'COPY ') && str_ends_with($line, ' FROM stdin;')) {
                $copy = true;
            } elseif ($copy && $line === '\\.') {
                $copy = false;
            }
        }

        return implode("\n", $output);
    }

    public static function fingerprints(PDO $db, string $schema): array
    {
        $query = $db->prepare('SELECT tablename FROM pg_tables WHERE schemaname = ? ORDER BY tablename');
        $query->execute([$schema]);
        $result = [];
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $rows = $db->query('SELECT row_to_json(t)::text FROM '.PostgresSchema::identifier($schema).'.'.PostgresSchema::identifier($table).' AS t ORDER BY row_to_json(t)::text');
            $hash = hash_init('sha256');
            $count = 0;
            while (($row = $rows->fetchColumn()) !== false) {
                hash_update($hash, strlen($row).':'.$row);
                $count++;
            }
            $result[$table] = ['rows' => $count, 'sha256' => hash_final($hash)];
        }

        return $result;
    }

    public static function drill(string $root, array $connection, string $source, string $appKey): array
    {
        if (preg_match('/^nfcheck_(online|probe_[a-f0-9]{16})$/D', $source) !== 1) {
            throw new \RuntimeException('Recovery acceptance requires an approved NFCheck source.');
        }
        $admin = PostgresCloud::connect($connection);
        $before = self::fingerprints($admin, $source);
        $id = bin2hex(random_bytes(8));
        $target = 'nfcheck_probe_'.bin2hex(random_bytes(8));
        $dump = $root.'/.tools/secrets/pg-'.$id.'-dump.sql';
        $environment = $root.'/.tools/secrets/pg-'.$id.'-environment.env';
        $restore = $root.'/.tools/secrets/pg-'.$id.'-restore.sql';
        $archive = $root.'/.tools/backups/postgres-probe-'.$id.'.zip';
        $restored = false;
        $targetCheck = $admin->prepare('SELECT 1 FROM pg_namespace WHERE nspname = ?');
        $targetCheck->execute([$target]);
        if ($targetCheck->fetchColumn()) {
            throw new \RuntimeException('Restore target already exists.');
        }
        $client = $root.'/.tools/postgres-client/17.11/';
        $env = ['PGHOST' => $connection['host'], 'PGPORT' => '5432', 'PGDATABASE' => 'postgres', 'PGUSER' => $connection['username'], 'PGPASSWORD' => $connection['password'], 'PGSSLMODE' => 'verify-full', 'PGSSLROOTCERT' => $connection['ca_file'], 'PGCONNECT_TIMEOUT' => '10'];
        try {
            $process = new Process([$client.'pg_dump.exe', '--no-owner', '--no-privileges', '--quote-all-identifiers', '--strict-names', '--schema='.$source, '--file='.$dump], $root, $env);
            $process->setTimeout(180);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('The private PostgreSQL dump failed.');
            }
            file_put_contents($environment, 'APP_KEY='.$appKey."\n");
            $key = trim(file_get_contents($root.'/.tools/secrets/backup-key.txt'));
            $crypto = new RecoveryArchive;
            $crypto->create($archive, ['database.sql' => $dump, 'environment.env' => $environment], $key, ['engine' => 'pgsql', 'schema' => $source, 'fingerprints' => $before]);
            $recovered = $crypto->read($archive, $key);
            file_put_contents($restore, self::remapDump($recovered['files']['database.sql'], $source, $target));
            $process = new Process([$client.'psql.exe', '--no-psqlrc', '--set=ON_ERROR_STOP=1', '--single-transaction', '--file='.$restore], $root, $env);
            $process->setTimeout(180);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('The isolated PostgreSQL restore failed and its transaction was rolled back.');
            }
            $restored = true;
            // Native dump/restore can outlive an idle pooler connection.
            $admin = PostgresCloud::connect($connection);
            $matches = $before === self::fingerprints($admin, $target);
            $sourceMatches = $before === self::fingerprints($admin, $source);
            if (! $matches || ! $sourceMatches) {
                throw new \RuntimeException('PostgreSQL restore fingerprints did not reconcile.');
            }

            return ['encrypted_archive_created' => true, 'table_fingerprints_matched' => count($before), 'source_unchanged' => $sourceMatches,
                'connection_fingerprint' => hash('sha256', $connection['host']."\0".$connection['database']."\0".$connection['username']."\0".hash_file('sha256', $connection['ca_file']))];
        } finally {
            if ($restored) {
                PostgresSchema::dropProbe($admin, $target);
            }
            foreach ([$dump, $environment, $restore] as $ownedFile) {
                if (is_file($ownedFile)) {
                    unlink($ownedFile);
                }
            }
        }
    }
}
