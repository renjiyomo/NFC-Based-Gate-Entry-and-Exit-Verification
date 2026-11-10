<?php

use App\Support\IsolatedDatabase;
use App\Support\SchemaDialect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        SchemaDialect::dropCheck('scan_attempts', 'attempt_outcome_review');
        $automatic = "(policy_version = 'gate-auto-v2' OR policy_version LIKE 'gate-state-v1-%')";
        DB::statement("ALTER TABLE scan_attempts ADD CONSTRAINT attempt_outcome_review CHECK ((outcome = 'denied' AND review_status = 'not_applicable' AND review_expires_at IS NULL) OR (outcome = 'allowed' AND review_status <> 'not_applicable' AND person_id IS NOT NULL AND credential_id IS NOT NULL AND credential_assignment_id IS NOT NULL AND person_version IS NOT NULL AND (($automatic AND review_status IN ('confirmed','cancelled') AND review_expires_at IS NULL) OR (NOT $automatic AND authorization_id IS NOT NULL AND review_expires_at IS NOT NULL AND review_expires_at > received_at))))");
    }

    public function down(): void
    {
        throw new RuntimeException('Retain automatic-direction history; use forward migrations.');
    }
};
