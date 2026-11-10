<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class DatabaseSql
{
    public static function manilaDate(): string
    {
        return DB::getDriverName() === 'pgsql' ? "DATE(confirmed_at + INTERVAL '8 hours')" : 'DATE(confirmed_at + INTERVAL 8 HOUR)';
    }

    public static function manilaHour(): string
    {
        return DB::getDriverName() === 'pgsql' ? "EXTRACT(HOUR FROM confirmed_at + INTERVAL '8 hours')" : 'HOUR(confirmed_at + INTERVAL 8 HOUR)';
    }
}
