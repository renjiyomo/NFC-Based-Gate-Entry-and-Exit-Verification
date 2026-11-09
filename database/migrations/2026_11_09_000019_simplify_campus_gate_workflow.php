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
        Schema::create('campus_programs', function (Blueprint $t): void {
            $t->string('code', 32)->primary();
            $t->string('name', 180);
            $t->unsignedTinyInteger('max_year_level')->default(4);
        });
        foreach (config('campus.programs') as $code => $name) {
            DB::table('campus_programs')->insert(['code' => $code, 'name' => $name, 'max_year_level' => 4]);
        }
        Schema::create('campus_departments', function (Blueprint $t): void {
            $t->string('name', 120)->primary();
        });
        foreach (config('campus.departments') as $name) {
            DB::table('campus_departments')->insert(['name' => $name]);
        }
        Schema::table('users', function (Blueprint $t): void {
            $t->string('username', 64)->nullable()->unique();
            $t->foreignId('gate_id')->nullable()->unique()->constrained('gates')->restrictOnDelete();
        });
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $u) {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', explode('@', $u->email)[0]));
            $username = substr($base ?: 'operator', 0, 48);
            if (DB::table('users')->where('username', $username)->exists()) {
                $username .= '-'.$u->id;
            }
            DB::table('users')->where('id', $u->id)->update(['username' => $username]);
        }
        Schema::table('student_profiles', function (Blueprint $t): void {
            $t->string('academic_year', 9)->nullable();
            $t->string('semester', 20)->nullable();
        });
        // Legacy observations remain intact; the new policy records an NFC scan,
        // not a human identity attestation. No grant/dated assignment is required.
        Schema::table('scan_attempts', fn (Blueprint $t) => $t->unsignedBigInteger('gate_assignment_id')->nullable()->change());
        DB::statement("ALTER TABLE access_events MODIFY identity_method ENUM('photo','campus_document','nfc_scan') NOT NULL");
        DB::statement('ALTER TABLE scan_attempts DROP CHECK attempt_outcome_review');
        DB::statement('ALTER TABLE scan_attempts DROP CHECK attempt_allowed_deadline_present');
        DB::statement("ALTER TABLE scan_attempts ADD CONSTRAINT attempt_outcome_review CHECK ((outcome = 'denied' AND review_status = 'not_applicable' AND review_expires_at IS NULL) OR (outcome = 'allowed' AND review_status <> 'not_applicable' AND person_id IS NOT NULL AND credential_id IS NOT NULL AND credential_assignment_id IS NOT NULL AND person_version IS NOT NULL AND ((policy_version = 'gate-auto-v2' AND review_status IN ('confirmed','cancelled') AND review_expires_at IS NULL) OR (policy_version <> 'gate-auto-v2' AND authorization_id IS NOT NULL AND review_expires_at IS NOT NULL AND review_expires_at > received_at))))");
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only workflow migration: retain legacy and automatic passage history.');
    }
};
