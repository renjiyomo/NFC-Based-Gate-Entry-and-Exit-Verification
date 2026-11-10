<?php

namespace App\Support;

use Illuminate\Database\Connection;

final class CloudMigrationTarget
{
    private static ?array $target = null;

    public static function authorize(string $root, string $schema): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new \RuntimeException('Cloud maintenance requires the private CLI runner.');
        }
        $c = PostgresCloud::load($root);
        if (! preg_match('/^nfcheck_(online|probe_[a-f0-9]{16})$/D', $schema)) {
            throw new \RuntimeException('Unrecognized cloud migration schema.');
        }
        self::$target = [...$c, 'schema' => $schema];
    }

    public static function assertTarget(Connection $connection): bool
    {
        if (self::$target === null) {
            return false;
        }
        $c = self::$target;
        if (PHP_SAPI !== 'cli' || $connection->getDriverName() !== 'pgsql' || $connection->getConfig('host') !== $c['host']
            || (int) $connection->getConfig('port') !== 5432 || $connection->getDatabaseName() !== 'postgres' || $connection->getConfig('username') !== $c['username']
            || $connection->getConfig('search_path') !== $c['schema'] || $connection->getConfig('sslmode') !== 'verify-full') {
            throw new \RuntimeException('Refusing cloud migrations on an unrecognized connection.');
        }
        $identity = $connection->selectOne('SELECT current_schema() AS schema, current_user AS actor, current_setting(\'server_version_num\')::integer AS version');
        if ($identity->schema !== $c['schema'] || $identity->actor !== explode('.', $c['username'])[0] || $identity->version < 150000) {
            throw new \RuntimeException('The PostgreSQL migration identity does not match the private target.');
        }

        return true;
    }

    public static function clear(): void
    {
        self::$target = null;
    }
}
