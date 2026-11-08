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
        Schema::create('persons', function (Blueprint $table): void {
            $table->id();
            $table->enum('person_type', ['student', 'employee']);
            $table->string('campus_number', 64);
            $table->string('full_name', 120);
            $table->string('photo_path')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps(6);
            $table->unique(['person_type', 'campus_number']);
            $table->index(['person_type', 'status']);
        });
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('persons');
    }
};
