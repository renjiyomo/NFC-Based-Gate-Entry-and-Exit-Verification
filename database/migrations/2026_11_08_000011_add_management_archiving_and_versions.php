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
        foreach (['persons', 'gates', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->softDeletes('archived_at', 6);
                $table->unsignedBigInteger('record_version')->default(1);
                $table->index(['archived_at', 'status']);
            });
        }
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        foreach (['persons', 'gates', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['archived_at', 'status']);
                $table->dropColumn(['archived_at', 'record_version']);
            });
        }
    }
};
