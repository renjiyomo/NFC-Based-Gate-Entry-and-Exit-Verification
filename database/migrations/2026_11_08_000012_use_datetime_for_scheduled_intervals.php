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
        Schema::table('access_authorizations', function (Blueprint $table): void {
            $table->dateTime('valid_from', 6)->change();
            $table->dateTime('valid_until', 6)->nullable()->change();
        });
        Schema::table('guard_gate_assignments', function (Blueprint $table): void {
            $table->dateTime('starts_at', 6)->change();
            $table->dateTime('ends_at', 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        IsolatedDatabase::assertMigrationTarget();
        foreach (['access_authorizations' => ['valid_from', 'valid_until'], 'guard_gate_assignments' => ['starts_at', 'ends_at']] as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::table($table)->where($column, '<', '1970-01-01 00:00:01')->orWhere($column, '>=', '2038-01-01 00:00:00')->exists()) {
                    throw new RuntimeException('Refusing to narrow schedule storage while dates exceed the safe TIMESTAMP range. Preserve data and review the rollback.');
                }
            }
        }
        Schema::table('guard_gate_assignments', function (Blueprint $table): void {
            $table->timestamp('starts_at', 6)->change();
            $table->timestamp('ends_at', 6)->nullable()->change();
        });
        Schema::table('access_authorizations', function (Blueprint $table): void {
            $table->timestamp('valid_from', 6)->change();
            $table->timestamp('valid_until', 6)->nullable()->change();
        });
    }
};
