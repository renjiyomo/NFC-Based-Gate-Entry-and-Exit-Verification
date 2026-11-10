<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class SchemaDialect
{
    public static function dropCheck(string $table, string $constraint): void
    {
        if (! preg_match('/^[a-z_]+$/D', $table) || ! preg_match('/^[a-z_]+$/D', $constraint)) {
            throw new \LogicException('Unexpected schema identifier.');
        }
        $kind = DB::getDriverName() === 'pgsql' ? 'CONSTRAINT' : 'CHECK';
        DB::statement("ALTER TABLE $table DROP $kind $constraint");
    }

    public static function allowAutomaticIdentity(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE access_events DROP CONSTRAINT access_events_identity_method_check');
            DB::statement("ALTER TABLE access_events ADD CONSTRAINT access_events_identity_method_check CHECK (identity_method IN ('photo','campus_document','nfc_scan'))");
        } else {
            DB::statement("ALTER TABLE access_events MODIFY identity_method ENUM('photo','campus_document','nfc_scan') NOT NULL");
        }
    }
}
