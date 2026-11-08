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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->nullable()->unique()->constrained('persons')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'guard']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('auth_version')->default(1);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at', 6)->nullable();
            $table->rememberToken();
            $table->timestamps(6);
            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('users');
    }
};
