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
        Schema::create('gates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps(6);
            $table->index('status');
        });
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('gates');
    }
};
