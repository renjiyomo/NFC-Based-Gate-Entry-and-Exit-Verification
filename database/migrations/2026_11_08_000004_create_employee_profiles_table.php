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
        Schema::create('employee_profiles', function (Blueprint $table): void {
            $table->foreignId('person_id')->primary()->constrained('persons')->restrictOnDelete();
            $table->string('department', 120);
            $table->string('position', 120);
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('employee_profiles');
    }
};
