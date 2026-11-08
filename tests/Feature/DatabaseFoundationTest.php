<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\AccessAuthorization;
use App\Models\AuditLog;
use App\Models\EmployeeProfile;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\Person;
use App\Models\StudentProfile;
use App\Services\AccountService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class DatabaseFoundationTest extends DatabaseTestCase
{
    public function test_schema_is_mysql_and_runtime_has_no_ddl_or_cross_schema_privileges(): void
    {
        $this->assertSame('nfcheck_testing', DB::selectOne('SELECT DATABASE() db')->db);
        $this->assertSame(13, DB::table('migrations')->count());
        $grants = json_encode(DB::select('SHOW GRANTS FOR CURRENT_USER'));
        foreach (['ALL PRIVILEGES', 'CREATE', 'ALTER', 'DROP', '`nfcheck`.'] as $privilege) {
            $this->assertStringNotContainsString($privilege, $grants);
        }
        $this->expectException(QueryException::class);
        DB::select('SELECT id FROM nfcheck.users LIMIT 1');
    }

    public function test_account_creation_normalizes_email_hashes_password_and_records_audit(): void
    {
        $user = app(AccountService::class)->create(['name' => 'Test administrator', 'email' => ' ADMIN@example.invalid ', 'password' => self::PASSWORD, 'role' => 'admin']);
        $this->assertSame('admin@example.invalid', $user->email);
        $this->assertStringStartsWith('$argon2id$', $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertSame(1, AuditLog::where('target_id', $user->id)->where('action', 'account.created')->count());
        $this->expectException(ValidationException::class);
        app(AccountService::class)->create(['name' => 'Duplicate', 'email' => 'ADMIN@example.invalid', 'password' => self::PASSWORD, 'role' => 'admin']);
    }

    public function test_account_and_audit_write_roll_back_together(): void
    {
        $audit = \Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        $service = new AccountService($audit);
        try {
            $service->create(['name' => 'Must roll back', 'email' => 'rollback@example.invalid', 'password' => self::PASSWORD, 'role' => 'guard']);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.invalid']);
    }

    public function test_last_active_admin_cannot_be_disabled_or_demoted(): void
    {
        $admin = $this->user();
        $service = app(AccountService::class);
        foreach ([[UserRole::Guard, RecordStatus::Active], [UserRole::Admin, RecordStatus::Inactive]] as [$role, $status]) {
            try {
                $service->changeAccess($admin, $role, $status);
                $this->fail('Expected last-administrator protection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('account', $exception->errors());
            }
            $this->assertTrue($admin->fresh()->isAdmin());
        }
        $this->user();
        $changed = $service->changeAccess($admin, UserRole::Guard, RecordStatus::Active);
        $this->assertTrue($changed->isGuard());
        $this->assertSame(2, $changed->auth_version);
    }

    public function test_guard_cannot_call_administrator_account_service(): void
    {
        $guard = $this->user(['role' => 'guard']);
        $this->expectException(AuthorizationException::class);
        app(AccountService::class)->changeAccess($guard, UserRole::Admin, RecordStatus::Active, $guard);
    }

    public function test_person_profiles_and_operator_relationships_preserve_identity_types(): void
    {
        $student = Person::create(['person_type' => 'student', 'campus_number' => 'S-TEST', 'full_name' => 'Test student', 'status' => 'active']);
        $employee = Person::create(['person_type' => 'employee', 'campus_number' => 'E-TEST', 'full_name' => 'Test employee', 'status' => 'active']);
        StudentProfile::create(['person_id' => $student->id, 'program' => 'Test program', 'year_level' => 1]);
        EmployeeProfile::create(['person_id' => $employee->id, 'department' => 'Test department', 'position' => 'Test position']);
        $user = $this->user(['person_id' => $employee->id]);
        $this->assertSame('Test program', $student->studentProfile->program);
        $this->assertSame('Test department', $employee->employeeProfile->department);
        $this->assertTrue($user->person->is($employee));
        $this->assertTrue($employee->operator->is($user));
        $this->expectException(\DomainException::class);
        EmployeeProfile::create(['person_id' => $student->id, 'department' => 'Invalid', 'position' => 'Invalid']);
    }

    public function test_person_type_cannot_change_through_a_normal_update(): void
    {
        $person = Person::create(['person_type' => 'student', 'campus_number' => 'S-TEST', 'full_name' => 'Test student']);
        $this->expectException(\DomainException::class);
        $person->update(['person_type' => 'employee']);
    }

    public static function invalidRecords(): array
    {
        return [
            'invalid role' => ['users', ['name' => 'Bad role', 'email' => 'bad@example.invalid', 'password' => 'not-a-real-hash', 'role' => 'superuser']],
            'missing person foreign key' => ['student_profiles', ['person_id' => 99999999, 'program' => 'Invalid']],
            'invalid person type' => ['persons', ['person_type' => 'visitor', 'campus_number' => 'V-TEST', 'full_name' => 'Invalid']],
        ];
    }

    #[DataProvider('invalidRecords')]
    public function test_database_rejects_invalid_records(string $table, array $attributes): void
    {
        $this->expectException(QueryException::class);
        DB::table($table)->insert($attributes);
    }

    public function test_uniqueness_and_interval_constraints_are_enforced_by_mysql(): void
    {
        $admin = $this->user();
        $guard = $this->user(['role' => 'guard']);
        $gate = Gate::create(['code' => 'TEST', 'name' => 'Test gate', 'status' => 'active']);
        try {
            GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'assigned_by' => $admin->id, 'starts_at' => now(), 'ends_at' => now()->subMinute()]);
            $this->fail('Expected interval constraint.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, $exception->errorInfo[1]);
        }
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Duplicate', 'email' => $admin->email, 'password' => 'opaque', 'role' => 'admin']);
    }

    public function test_inactive_person_overrides_an_otherwise_effective_authorization(): void
    {
        $this->travelTo(now()->startOfSecond());
        $person = Person::create(['person_type' => 'student', 'campus_number' => 'S-TEST', 'full_name' => 'Test student', 'status' => 'active']);
        $admin = $this->user();
        $authorization = AccessAuthorization::create(['person_id' => $person->id, 'granted_by' => $admin->id, 'status' => 'active', 'valid_from' => now(), 'valid_until' => now()->addHour()]);
        $this->assertSame(1, AccessAuthorization::effective()->count());
        $this->assertTrue($authorization->person->is($person));
        $this->assertTrue($authorization->grantedBy->is($admin));
        $person->update(['status' => 'inactive']);
        $this->assertSame(0, AccessAuthorization::effective()->count());
        $person->update(['status' => 'active']);
        $this->travelTo($authorization->valid_until);
        $this->assertSame(0, AccessAuthorization::effective()->count());
        $this->travelBack();
    }

    public function test_audit_metadata_is_allowlisted_and_eloquent_mutation_is_rejected(): void
    {
        $audit = app(AuditLogger::class)->record('test.redaction', metadata: ['reason' => 'validation', 'password' => 'secret', 'token' => 'secret', 'email' => 'private@example.invalid', 'session_id' => 'secret']);
        $this->assertSame(['reason' => 'validation'], $audit->metadata);
        $this->expectException(\LogicException::class);
        $audit->update(['action' => 'tampered']);
    }

    public function test_audit_rows_cannot_be_updated_or_deleted_by_runtime_sql(): void
    {
        $audit = app(AuditLogger::class)->record('test.append_only');
        foreach (['UPDATE audit_logs SET action = ? WHERE id = ?', 'DELETE FROM audit_logs WHERE action = ? AND id = ?'] as $sql) {
            try {
                DB::statement($sql, ['tampered', $audit->id]);
                $this->fail('Expected permission denial.');
            } catch (QueryException $exception) {
                $this->assertSame(1142, $exception->errorInfo[1]);
            }
        }
        $this->assertDatabaseHas('audit_logs', ['id' => $audit->id, 'action' => 'test.append_only']);
    }
}
