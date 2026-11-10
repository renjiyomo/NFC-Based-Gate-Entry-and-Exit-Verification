<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use Pdo\Pgsql;

final class PostgresCloud
{
    public static function load(string $root): array
    {
        $all = json_decode(ltrim(file_get_contents($root.'/.tools/secrets/cloud-services.json'), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
        $c = $all['postgres'] ?? [];
        foreach (['host', 'username', 'password'] as $field) {
            if (! is_string($c[$field] ?? null) || trim($c[$field]) === '') {
                throw new \RuntimeException('Missing private PostgreSQL field: '.$field);
            }
        }
        if (preg_match('/^(db\.[a-z0-9]+\.supabase\.co|aws-[0-9]+-[a-z0-9-]+\.pooler\.supabase\.com)$/D', $c['host']) !== 1 || (int) $c['port'] !== 5432 || $c['database'] !== 'postgres') {
            throw new \RuntimeException('Use the approved Supabase direct/session connection parameters.');
        }
        if (($c['schema'] ?? '') !== 'nfcheck_online') {
            throw new \RuntimeException('The intended application schema must be nfcheck_online.');
        }
        $c['ca_file'] = ($c['ca_file'] ?? '') ?: $root.'/.tools/php/cacert.pem';
        if (! is_readable($c['ca_file']) || str_contains($c['ca_file'], "'") || str_contains($c['ca_file'], ';')) {
            throw new \RuntimeException('The database CA certificate must be a readable private file.');
        }

        return $c;
    }

    public static function connect(array $c): PDO
    {
        $ca = str_replace('\\', '/', $c['ca_file']);

        return new PDO("pgsql:host={$c['host']};port=5432;dbname=postgres;sslmode=verify-full;sslrootcert='$ca';connect_timeout=10;keepalives=1;keepalives_idle=10;keepalives_interval=5;keepalives_count=3;tcp_user_timeout=30000", $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, Pgsql::ATTR_DISABLE_PREPARES => true]);
    }

    public static function configure(array $c, string $schema): void
    {
        if (! preg_match('/^nfcheck_(online|probe_[a-f0-9]{16})$/D', $schema)) {
            throw new \RuntimeException('Unrecognized NFCheck PostgreSQL schema.');
        }
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => [
            'driver' => 'pgsql', 'host' => $c['host'], 'port' => 5432, 'database' => 'postgres', 'username' => $c['username'], 'password' => $c['password'],
            'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'search_path' => $schema, 'sslmode' => 'verify-full', 'sslrootcert' => $c['ca_file'], 'timezone' => '+00:00', 'application_name' => 'nfcheck-maintenance', 'options' => [PDO::ATTR_EMULATE_PREPARES => false, Pgsql::ATTR_DISABLE_PREPARES => true],
        ]]);
        DB::purge('pgsql');
    }
}
