<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Gate as Authorization;
use Tests\DatabaseTestCase;

class AuthorizationTest extends DatabaseTestCase
{
    public function test_admin_and_guard_workspaces_are_restricted_on_the_server(): void
    {
        $admin = $this->user();
        $guard = $this->user(['role' => 'guard']);
        $this->signIn($admin)->get('/admin/dashboard')->assertOk();
        $this->get('/guard/scanner')->assertForbidden();
        $this->get('/admin/scanner')->assertNotFound();
        $this->signIn($guard)->get('/guard/scanner')->assertOk();
        $this->get('/guard/activity')->assertOk();
        foreach (['dashboard', 'students', 'employees', 'credentials', 'guards', 'gates', 'history', 'reports', 'analytics', 'settings'] as $page) {
            $this->get('/admin/'.$page)->assertForbidden();
        }
    }

    public function test_policies_deny_guard_management_permissions(): void
    {
        $guard = $this->user(['role' => 'guard']);
        $admin = $this->user();
        foreach ([User::class, Person::class, Gate::class] as $model) {
            $this->assertTrue(Authorization::forUser($admin)->allows('viewAny', $model));
            $this->assertTrue(Authorization::forUser($admin)->allows('create', $model));
            $this->assertFalse(Authorization::forUser($guard)->allows('viewAny', $model));
            $this->assertFalse(Authorization::forUser($guard)->allows('create', $model));
        }
    }

    public function test_gate_operation_requires_the_exact_active_assignment_and_active_gate(): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = $this->user();
        $guard = $this->user(['role' => 'guard']);
        $gate = Gate::create(['code' => 'TEST-A', 'name' => 'Test station A', 'status' => 'active']);
        $other = Gate::create(['code' => 'TEST-B', 'name' => 'Test station B', 'status' => 'active']);
        $this->signIn($guard)->get('/guard/gates/'.$gate->id.'/scanner')->assertForbidden();
        $assignment = GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'assigned_by' => $admin->id]);
        $this->get('/guard/gates/'.$gate->id.'/scanner')->assertOk();
        $this->get('/guard/gates/'.$other->id.'/scanner')->assertForbidden();
        $gate->update(['status' => 'inactive']);
        $this->get('/guard/gates/'.$gate->id.'/scanner')->assertForbidden();
        $gate->update(['status' => 'active']);
        $assignment->update(['revoked_at' => now(), 'revoked_by' => $admin->id]);
        $this->get('/guard/gates/'.$gate->id.'/scanner')->assertForbidden();
        $assignment->update(['revoked_at' => null, 'revoked_by' => null]);
        $this->travelTo($assignment->ends_at);
        $this->get('/guard/gates/'.$gate->id.'/scanner')->assertForbidden();
        $this->travelBack();
    }
}
