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
        Schema::create('credential_counters', function (Blueprint $t): void {
            $t->string('kind', 16)->primary();
            $t->unsignedBigInteger('last_number')->default(0);
        });
        foreach (['student', 'employee'] as $kind) {
            $prefix = 'TAG-'.strtoupper($kind).'-';
            $last = 0;
            foreach (DB::table('nfc_credentials')->whereRaw('LOWER(label) LIKE ?', [strtolower($prefix).'%'])->pluck('label') as $label) {
                if (preg_match('/^'.preg_quote($prefix, '/').'([0-9]+)$/iD', $label, $m)) {
                    $number = filter_var(ltrim($m[1], '0') ?: '0', FILTER_VALIDATE_INT);
                    if ($number === false || $number === PHP_INT_MAX) {
                        throw new RuntimeException('Existing credential number exceeds the supported range.');
                    }
                    $last = max($last, $number);
                }
            }
            DB::table('credential_counters')->insert(['kind' => $kind, 'last_number' => $last]);
        }
        DB::statement("ALTER TABLE credential_counters ADD CONSTRAINT credential_counter_kind CHECK (kind IN ('student','employee'))");
        DB::statement('ALTER TABLE credential_counters ADD CONSTRAINT credential_counter_nonnegative CHECK (last_number >= 0)');
    }

    public function down(): void
    {
        throw new RuntimeException('Credential numbering is forward-only.');
    }
};
