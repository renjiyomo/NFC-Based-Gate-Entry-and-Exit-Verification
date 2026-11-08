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
        Schema::create('guard_gate_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guard_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('gate_id')->constrained('gates')->restrictOnDelete();
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6)->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['guard_id', 'gate_id', 'starts_at', 'ends_at'], 'guard_gate_effective_index');
        });
        DB::statement('ALTER TABLE guard_gate_assignments ADD CONSTRAINT assignment_valid_interval CHECK (ends_at IS NULL OR ends_at > starts_at)');
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('guard_gate_assignments');
    }
};
