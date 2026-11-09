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
        Schema::create('anomaly_flags', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->enum('source', ['physical', 'simulated']);
            $t->string('rule_code', 48);
            $t->char('rule_version', 64);
            $t->json('policy');
            $t->enum('severity', ['low', 'medium']);
            $t->foreignId('anchor_attempt_id')->constrained('scan_attempts')->restrictOnDelete();
            $t->text('explanation');
            $t->dateTime('occurred_at', 6);
            $t->dateTime('created_at', 6);
            $t->unique(['source', 'rule_code', 'rule_version', 'anchor_attempt_id'], 'anomaly_detection_identity');
            $t->index(['source', 'occurred_at', 'id'], 'anomaly_source_clock');
        });
        Schema::create('anomaly_evidence', function (Blueprint $t): void {
            $t->foreignId('anomaly_flag_id')->constrained('anomaly_flags')->restrictOnDelete();
            $t->foreignId('scan_attempt_id')->constrained('scan_attempts')->restrictOnDelete();
            $t->primary(['anomaly_flag_id', 'scan_attempt_id']);
        });
        Schema::create('anomaly_reviews', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('anomaly_flag_id')->constrained('anomaly_flags')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['open', 'in_review', 'resolved', 'false_alarm']);
            $t->text('reason');
            $t->dateTime('created_at', 6);
            $t->index(['anomaly_flag_id', 'id']);
        });
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('anomaly_reviews');
        Schema::dropIfExists('anomaly_evidence');
        Schema::dropIfExists('anomaly_flags');
    }
};
