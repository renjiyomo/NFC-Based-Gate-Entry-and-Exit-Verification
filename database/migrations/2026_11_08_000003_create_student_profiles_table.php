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
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->foreignId('person_id')->primary()->constrained('persons')->restrictOnDelete();
            $table->string('program', 120);
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->string('academic_term', 50)->nullable();
            $table->timestamps(6);
        });
        DB::statement('ALTER TABLE student_profiles ADD CONSTRAINT student_year_positive CHECK (year_level IS NULL OR year_level > 0)');
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        Schema::dropIfExists('student_profiles');
    }
};
