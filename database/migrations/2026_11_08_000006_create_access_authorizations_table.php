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
        Schema::create('access_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamp('valid_from', 6);
            $table->timestamp('valid_until', 6)->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['person_id', 'status', 'valid_from', 'valid_until'], 'authorizations_effective_index');
        });
        DB::statement('ALTER TABLE access_authorizations ADD CONSTRAINT authorization_valid_interval CHECK (valid_until IS NULL OR valid_until > valid_from)');
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('access_authorizations');
    }
};
