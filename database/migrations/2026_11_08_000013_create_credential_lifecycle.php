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
        Schema::create('nfc_credentials', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('label', 64)->unique();
            $table->binary('token_digest', 32, true)->unique();
            $table->enum('status', ['pending', 'active', 'revoked', 'cancelled', 'expired'])->default('pending');
            $table->enum('source', ['physical', 'simulated']);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('issuer_auth_version');
            $table->uuid('issuance_request_id');
            $table->binary('completion_digest', 32, true);
            $table->binary('issuer_session_digest', 32, true);
            $table->unsignedBigInteger('person_version');
            $table->enum('issuance_mode', ['new', 'replace', 'lost']);
            $table->foreignId('replaces_credential_id')->nullable()->constrained('nfc_credentials')->restrictOnDelete();
            $table->dateTime('pending_expires_at', 6);
            $table->dateTime('read_back_at', 6)->nullable();
            $table->dateTime('activated_at', 6)->nullable();
            $table->dateTime('valid_until', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('close_reason', 40)->nullable();
            $table->timestamps(6);
            $table->unique(['created_by', 'issuance_request_id']);
            $table->index(['status', 'pending_expires_at']);
            $table->index(['source', 'status', 'created_at']);
        });
        Schema::create('credential_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignId('credential_id')->unique()->constrained('nfc_credentials')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at', 6);
            $table->dateTime('ended_at', 6)->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('end_reason', 40)->nullable();
            $table->unique(['person_id', 'id']);
            $table->index(['person_id', 'assigned_at']);
        });
        Schema::table('persons', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_credential_assignment_id')->nullable()->unique();
            $table->foreign(['id', 'active_credential_assignment_id'], 'person_active_credential_owner_fk')->references(['person_id', 'id'])->on('credential_assignments')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE nfc_credentials ADD CONSTRAINT credential_expiry_after_creation CHECK (valid_until IS NULL OR valid_until > created_at)');
        DB::statement('ALTER TABLE credential_assignments ADD CONSTRAINT credential_assignment_end_order CHECK (ended_at IS NULL OR ended_at >= assigned_at)');
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::table('persons', function (Blueprint $table): void {
            $table->dropForeign('person_active_credential_owner_fk');
            $table->dropUnique(['active_credential_assignment_id']);
            $table->dropColumn('active_credential_assignment_id');
        });
        Schema::dropIfExists('credential_assignments');
        Schema::dropIfExists('nfc_credentials');
    }
};
