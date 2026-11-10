<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DatabaseLocks
{
    // Callers lock a mutable owner/anchor or read a committed unique winner. PostgreSQL
    // row-lock clauses would additionally require UPDATE on immutable history.
    public static function immutableRead(Builder $query): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('History decisions require their owner transaction.');
        }
        if (DB::getDriverName() !== 'pgsql') {
            $query->sharedLock();
        }
    }
}
