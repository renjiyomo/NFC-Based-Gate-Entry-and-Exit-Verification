<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\ManagementTestCase;

class GateAndGuardManagementTest extends ManagementTestCase
{
    public function test_failed_setup_delivery_preserves_the_account_but_removes_the_undelivered_token(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('Private SMTP diagnostic'));
        $response = $this->post('/admin/guards', ['name' => 'Synthetic mail failure', 'email' => 'delivery-failure@example.invalid'])->assertSessionHasNoErrors();
        $guard = User::where('email', 'delivery-failure@example.invalid')->sole();
        $response->assertRedirect('/admin/guards/'.$guard->id);
        $this->assertStringNotContainsString('Private SMTP diagnostic', session('status'));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $guard->email]);
        $this->assertTrue($guard->isGuard());
    }

    public function test_gate_crud_normalization_uniqueness_and_stale_update_protection(): void
    {
        $this->get('/admin/gates/create')->assertOk();
        $this->post('/admin/gates', ['code' => ' test-main ', 'name' => 'Synthetic main station', 'status' => 'active'])->assertSessionHasNoErrors()->assertRedirect();
        $gate = Gate::where('code', 'TEST-MAIN')->sole();
        $this->get('/admin/gates/'.$gate->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('management/GateShow')->where('record.name', 'Synthetic main station'));
        $this->get('/admin/gates/'.$gate->id.'/edit')->assertOk();
        $this->post('/admin/gates', ['code' => 'TEST-MAIN', 'name' => 'Duplicate', 'status' => 'active'])->assertSessionHasErrors('code');
        $data = ['code' => 'TEST-MAIN', 'name' => 'Updated synthetic station', 'status' => 'inactive', 'record_version' => 1];
        $this->put('/admin/gates/'.$gate->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('Updated synthetic station', $gate->fresh()->name);
        $this->put('/admin/gates/'.$gate->id, [...$data, 'name' => 'Stale overwrite'])->assertSessionHasErrors('record_version')->assertRedirect('/admin/gates/'.$gate->id.'/edit');
        $this->post('/admin/gates', ['code' => '../unsafe', 'name' => '', 'status' => 'unknown'])->assertSessionHasErrors(['code', 'name', 'status']);
        $this->get('/admin/gates?q=Updated&status=inactive')->assertInertia(fn (Assert $page) => $page->where('records.total', 1));
    }

    public function test_gate_archive_revokes_assignments_and_restore_stays_inactive(): void
    {
        $gate = Gate::create(['code' => 'TEST-A', 'name' => 'Synthetic station', 'status' => 'active'])->fresh();
        $guard = $this->user(['role' => 'guard']);
        $assignment = GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subMinute()]);
        $this->delete('/admin/gates/'.$gate->id, ['record_version' => 1])->assertRedirect('/admin/gates');
        $this->assertNotNull($assignment->fresh()->revoked_at);
        $this->assertTrue(Gate::withTrashed()->find($gate->id)->trashed());
        $this->get('/admin/gates?records=archived')->assertInertia(fn (Assert $page) => $page->where('records.total', 1));
        $this->post('/admin/gates/'.$gate->id.'/restore', ['record_version' => 2])->assertSessionHasNoErrors();
        $this->assertSame('inactive', Gate::findOrFail($gate->id)->status->value);
        $this->assertNotNull($assignment->fresh()->revoked_at);
    }

    public function test_guard_creation_has_fixed_role_and_sends_private_setup_notification(): void
    {
        Notification::fake();
        $this->get('/admin/guards/create')->assertOk();
        $this->post('/admin/guards', ['name' => 'Synthetic guard', 'email' => ' GUARD@example.invalid ', 'role' => 'admin', 'password' => 'injected'])->assertSessionHasNoErrors()->assertRedirect();
        $guard = User::where('email', 'guard@example.invalid')->sole();
        $this->assertTrue($guard->isGuard());
        $this->assertTrue($guard->must_change_password);
        $this->assertStringStartsWith('$argon2id$', $guard->password);
        Notification::assertSentTo($guard, ResetPassword::class);
        $this->get('/admin/guards/'.$guard->id)->assertInertia(fn (Assert $page) => $page->component('management/GuardShow')->where('record.email', $guard->email)->missing('record.password')->missing('record.auth_version'));
        $this->get('/admin/guards')->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.name', 'Synthetic guard'));
        $this->post('/admin/guards', ['name' => 'Duplicate', 'email' => $guard->email])->assertSessionHasErrors('email');
        $this->get('/admin/guards/'.$this->admin->id)->assertForbidden();
        $this->put('/admin/guards/'.$this->admin->id, [])->assertForbidden();
        $this->delete('/admin/guards/'.$this->admin->id, ['record_version' => 1])->assertForbidden();
        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_guard_update_archive_and_restore_invalidate_access_without_mutating_admins(): void
    {
        $guard = $this->user(['role' => 'guard'])->fresh();
        $gate = Gate::create(['code' => 'TEST', 'name' => 'Test station', 'status' => 'active']);
        $assignment = GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subMinute()]);
        DB::table('sessions')->insert(['id' => 'guard-session', 'user_id' => $guard->id, 'payload' => 'opaque', 'last_activity' => time()]);
        Password::createToken($guard);
        $this->put('/admin/guards/'.$guard->id, ['name' => 'Updated synthetic guard', 'status' => 'inactive', 'record_version' => 1, 'role' => 'admin', 'email' => 'changed@example.invalid'])->assertSessionHasNoErrors();
        $this->assertSame('Updated synthetic guard', $guard->fresh()->name);
        $this->assertSame('guard', $guard->fresh()->role->value);
        $this->assertSame($guard->email, $guard->fresh()->email);
        $this->assertSame(2, $guard->fresh()->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'guard-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $guard->email]);
        $this->delete('/admin/guards/'.$guard->id, ['record_version' => 2])->assertRedirect('/admin/guards');
        $this->assertNotNull($assignment->fresh()->revoked_at);
        $this->assertTrue(User::withTrashed()->findOrFail($guard->id)->trashed());
        $this->post('/admin/guards/'.$guard->id.'/restore', ['record_version' => 3])->assertSessionHasNoErrors();
        $this->assertSame('inactive', User::findOrFail($guard->id)->status->value);
        $this->assertNotNull($assignment->fresh()->revoked_at);
    }

    public function test_guards_cannot_access_management_reads_writes_or_photos(): void
    {
        $person = $this->makePerson();
        $guard = $this->user(['role' => 'guard']);
        $gate = Gate::create(['code' => 'TEST', 'name' => 'Synthetic station', 'status' => 'active']);
        $this->signIn($guard);
        foreach (['/admin/students/create', '/admin/employees/create', '/admin/gates/create', '/admin/guards/create', '/admin/students/'.$person->id, '/admin/students/'.$person->id.'/photo', '/admin/gates/'.$gate->id, '/admin/guards/'.$guard->id] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach (['/admin/students', '/admin/employees', '/admin/gates', '/admin/guards', '/admin/gates/'.$gate->id.'/assignments', '/admin/students/'.$person->id.'/grants', '/admin/guards/'.$guard->id.'/setup-link'] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        $this->put('/admin/students/'.$person->id, $this->student(['record_version' => 1]))->assertForbidden();
        $this->delete('/admin/gates/'.$gate->id, ['record_version' => 1])->assertForbidden();
        $this->assertSame(1, Person::count());
    }
}
