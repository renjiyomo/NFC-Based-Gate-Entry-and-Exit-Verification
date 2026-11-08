<?php

use App\Support\IsolatedDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::create('scan_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('request_id');
            $table->foreignId('guard_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('guard_auth_version');
            $table->binary('session_digest', 32, true);
            $table->foreignId('gate_id')->constrained('gates')->restrictOnDelete();
            $table->foreignId('gate_assignment_id')->constrained('guard_gate_assignments')->restrictOnDelete();
            $table->binary('payload_fingerprint', 32, true);
            $table->enum('direction', ['entry', 'exit']);
            $table->enum('source', ['physical', 'simulated']);
            $table->foreignId('person_id')->nullable()->constrained('persons')->restrictOnDelete();
            $table->unsignedBigInteger('person_version')->nullable();
            $table->foreignId('credential_id')->nullable()->constrained('nfc_credentials')->restrictOnDelete();
            $table->foreignId('credential_assignment_id')->nullable()->constrained('credential_assignments')->restrictOnDelete();
            $table->foreignId('authorization_id')->nullable()->constrained('access_authorizations')->restrictOnDelete();
            $table->enum('outcome', ['allowed', 'denied']);
            $table->string('reason', 64);
            $table->string('policy_version', 32);
            $table->unsignedSmallInteger('duplicate_window_seconds');
            $table->dateTime('received_at', 6);
            $table->dateTime('review_expires_at', 6)->nullable();
            $table->enum('review_status', ['pending', 'not_applicable', 'confirmed', 'identity_mismatch', 'cancelled', 'expired']);
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('review_reason', 64)->nullable();
            $table->unsignedBigInteger('duplicate_of_event_id')->nullable();
            $table->timestamps(6);
            $table->unique(['guard_id', 'request_id']);
            $table->unique(['id', 'person_id', 'credential_assignment_id', 'guard_id', 'gate_id', 'direction', 'source'], 'attempt_event_identity_unique');
            $table->index(['gate_id', 'received_at', 'id']);
            $table->index(['review_status', 'review_expires_at']);
        });
        Schema::create('access_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('scan_attempt_id')->unique()->constrained('scan_attempts')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignId('credential_assignment_id')->constrained('credential_assignments')->restrictOnDelete();
            $table->foreignId('guard_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('gate_id')->constrained('gates')->restrictOnDelete();
            $table->enum('direction', ['entry', 'exit']);
            $table->enum('source', ['physical', 'simulated']);
            $table->string('policy_version', 32);
            $table->enum('identity_method', ['photo', 'campus_document']);
            $table->string('sequence_notice', 40)->nullable();
            $table->dateTime('confirmed_at', 6);
            $table->foreign(['scan_attempt_id', 'person_id', 'credential_assignment_id', 'guard_id', 'gate_id', 'direction', 'source'], 'event_attempt_identity_fk')->references(['id', 'person_id', 'credential_assignment_id', 'guard_id', 'gate_id', 'direction', 'source'])->on('scan_attempts')->restrictOnDelete();
            $table->index(['person_id', 'gate_id', 'direction', 'source', 'confirmed_at'], 'passage_duplicate_lookup');
            $table->index(['confirmed_at', 'id']);
        });
        Schema::table('scan_attempts', fn (Blueprint $table) => $table->foreign('duplicate_of_event_id')->references('id')->on('access_events')->restrictOnDelete());
        DB::statement("ALTER TABLE scan_attempts ADD CONSTRAINT attempt_outcome_review CHECK ((outcome = 'denied' AND review_status = 'not_applicable' AND review_expires_at IS NULL) OR (outcome = 'allowed' AND review_status <> 'not_applicable' AND person_id IS NOT NULL AND credential_id IS NOT NULL AND credential_assignment_id IS NOT NULL AND authorization_id IS NOT NULL AND person_version IS NOT NULL AND review_expires_at > received_at))");
        DB::statement("ALTER TABLE scan_attempts ADD CONSTRAINT attempt_review_clock CHECK ((review_status IN ('pending', 'not_applicable') AND reviewed_at IS NULL) OR (review_status IN ('confirmed', 'identity_mismatch', 'cancelled', 'expired') AND reviewed_at IS NOT NULL))");
        DB::statement("ALTER TABLE scan_attempts ADD CONSTRAINT attempt_duplicate_review CHECK (duplicate_of_event_id IS NULL OR (review_status = 'cancelled' AND review_reason = 'duplicate_passage'))");
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::table('scan_attempts', fn (Blueprint $table) => $table->dropForeign(['duplicate_of_event_id']));
        Schema::dropIfExists('access_events');
        Schema::dropIfExists('scan_attempts');
    }
};
