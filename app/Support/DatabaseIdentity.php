<?php

namespace App\Support;

final class DatabaseIdentity
{
    public static function name(): string
    {
        $connection = config('database.connections.'.config('database.default'));

        return (string) ($connection['driver'] === 'pgsql' ? $connection['search_path'] : $connection['database']);
    }
}
