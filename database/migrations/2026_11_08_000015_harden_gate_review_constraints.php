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
        // MySQL accepts UNKNOWN in CHECK expressions: explicitly reject nulls.
        DB::statement("ALTER TABLE scan_attempts ADD CONSTRAINT attempt_allowed_deadline_present CHECK (outcome <> 'allowed' OR review_expires_at IS NOT NULL)");
        DB::statement('ALTER TABLE scan_attempts ADD CONSTRAINT attempt_duplicate_reason_present CHECK (duplicate_of_event_id IS NULL OR review_reason IS NOT NULL)');
        DB::statement("ALTER TABLE scan_attempts ADD CONSTRAINT attempt_reviewer_owner CHECK ((reviewed_by IS NULL OR reviewed_by = guard_id) AND (review_status NOT IN ('confirmed', 'identity_mismatch', 'cancelled') OR reviewed_by IS NOT NULL))");
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        foreach (['attempt_allowed_deadline_present', 'attempt_duplicate_reason_present', 'attempt_reviewer_owner'] as $constraint) {
            SchemaDialect::dropCheck('scan_attempts', $constraint);
        }
    }
};
