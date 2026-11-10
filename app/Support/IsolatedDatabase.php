<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class IsolatedDatabase
{
    public static function assertMigrationTarget(): void
    {
        $connection = DB::connection();
        if (CloudMigrationTarget::assertTarget($connection)) {
            return;
        }
        if ($connection->getDriverName() !== 'mysql' || $connection->getConfig('host') !== '127.0.0.1'
            || (int) $connection->getConfig('port') !== 3307 || ! in_array($connection->getDatabaseName(), ['nfcheck', 'nfcheck_testing', 'nfcheck_simulator', 'nfcheck_hardware'], true)) {
            throw new \RuntimeException('Migrations require an isolated NFCheck schema on loopback port 3307.');
        }
        $identity = $connection->selectOne('SELECT VERSION() version, @@datadir data_directory');
        $expected = realpath(base_path('.tools/mysql-data'));
        if ($expected === false || $identity->version !== '8.4.13'
            || strcasecmp(rtrim(str_replace('\\', '/', $identity->data_directory), '/'), str_replace('\\', '/', $expected)) !== 0) {
            throw new \RuntimeException('Refusing migrations on an unrecognized MySQL data directory.');
        }
    }
}
