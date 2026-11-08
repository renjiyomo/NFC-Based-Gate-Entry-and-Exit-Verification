<?php

use App\Support\IsolatedDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::create('passage_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('access_event_id')->constrained('access_events')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->enum('action', ['note', 'void', 'reinstate']);
            $table->text('reason');
            $table->dateTime('created_at', 6);
            $table->index(['access_event_id', 'action', 'id'], 'correction_decision_lookup');
        });
        Schema::table('access_events', function (Blueprint $table): void {
            $table->index(['source', 'confirmed_at', 'id'], 'event_source_clock');
            $table->index(['source', 'person_id', 'confirmed_at', 'id'], 'event_presence_lookup');
            $table->index(['source', 'gate_id', 'confirmed_at', 'id'], 'event_source_gate_clock');
        });
        Schema::table('scan_attempts', fn (Blueprint $table) => $table->index(['source', 'received_at', 'id'], 'attempt_source_clock'));
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::table('scan_attempts', fn (Blueprint $table) => $table->dropIndex('attempt_source_clock'));
        Schema::table('access_events', function (Blueprint $table): void {
            $table->dropIndex('event_source_clock');
            $table->dropIndex('event_presence_lookup');
            $table->dropIndex('event_source_gate_clock');
        });
        Schema::dropIfExists('passage_corrections');
    }
};
