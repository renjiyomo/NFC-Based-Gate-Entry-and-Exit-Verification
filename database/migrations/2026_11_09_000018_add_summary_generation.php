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
        Schema::create('summary_requests', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->uuid('request_key');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('actor_auth_version');
            $t->enum('source', ['physical', 'simulated']);
            $t->string('period', 10);
            $t->date('period_from');
            $t->date('period_to');
            $t->dateTime('snapshot_at', 6);
            $t->json('snapshot');
            $t->char('snapshot_hash', 64);
            $t->string('contract_version', 32);
            $t->dateTime('created_at', 6);
            $t->unique(['actor_id', 'request_key']);
            $t->index(['source', 'created_at', 'id']);
        });
        Schema::create('summary_tasks', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('summary_request_id')->unique()->constrained('summary_requests')->restrictOnDelete();
            $t->dateTime('claimed_at', 6)->nullable();
            $t->dateTime('created_at', 6);
        });
        Schema::create('summary_results', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('summary_request_id')->unique()->constrained('summary_requests')->restrictOnDelete();
            $t->enum('status', ['ready', 'failed', 'unavailable', 'rejected']);
            $t->json('selected_facts')->nullable();
            $t->json('sentences')->nullable();
            $t->string('error_code', 40)->nullable();
            $t->string('provider', 32);
            $t->string('model', 100)->nullable();
            $t->unsignedInteger('input_tokens')->nullable();
            $t->unsignedInteger('output_tokens')->nullable();
            $t->dateTime('created_at', 6);
        });
        Schema::create('summary_reviews', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('summary_request_id')->constrained('summary_requests')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->enum('decision', ['approved', 'rejected']);
            $t->text('reason');
            $t->dateTime('created_at', 6);
            $t->index(['summary_request_id', 'id']);
        });
        Schema::create('summary_controls', function (Blueprint $t): void {
            $t->unsignedTinyInteger('id')->primary();
            $t->date('quota_day');
            $t->unsignedInteger('claims');
        });
        DB::table('summary_controls')->insert(['id' => 1, 'quota_day' => '2000-01-01', 'claims' => 0]);
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('summary_controls');
        Schema::dropIfExists('summary_reviews');
        Schema::dropIfExists('summary_results');
        Schema::dropIfExists('summary_tasks');
        Schema::dropIfExists('summary_requests');
    }
};
