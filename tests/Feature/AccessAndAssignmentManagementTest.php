<?php

namespace Tests\Feature;

use App\Models\AccessAuthorization;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Services\AuditLogger;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\ManagementTestCase;

class AccessAndAssignmentManagementTest extends ManagementTestCase
{
    public function test_schedules_beyond_2038_are_stored_without_overflow(): void
    {
        $person = $this->makePerson();
        $this->post('/admin/students/'.$person->id.'/grants', ['starts_at' => '2040-01-01T08:00', 'ends_at' => '2041-01-01T08:00', 'reason' => 'Future schedule test', 'record_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2040-01-01 00:00:00', AccessAuthorization::sole()->valid_from->format('Y-m-d H:i:s'));
        $guard = $this->user(['role' => 'guard']);
        $gate = Gate::create(['code' => 'FUTURE', 'name' => 'Future station', 'status' => 'active']);
        $this->post('/admin/gates/'.$gate->id.'/assignments', ['guard_id' => $guard->id, 'gate_id' => $gate->id, 'starts_at' => '2040-01-01T08:00', 'ends_at' => '2041-01-01T08:00'])->assertSessionHasNoErrors();
        $this->assertSame('2041-01-01 00:00:00', GuardGateAssignment::sole()->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_access_grants_use_manila_time_enforce_boundaries_and_filter_current_access(): void
    {
        $person = $this->makePerson();
        $base = '/admin/students/'.$person->id;
        $data = [...$this->schedule(), 'reason' => 'Synthetic authorized test grant', 'record_version' => 1];
        $this->post($base.'/grants', $data)->assertSessionHasNoErrors()->assertRedirect($base);
        $grant = AccessAuthorization::sole();
        $this->assertSame($data['starts_at'], $grant->valid_from->setTimezone('Asia/Manila')->format('Y-m-d\TH:i'));
        $this->get('/admin/students?access=authorized')->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.access_active', true));
        $this->post($base.'/grants', [...$data, 'record_version' => 2])->assertSessionHasErrors('starts_at')->assertRedirect($base);
        $this->assertSame(1, AccessAuthorization::count());
        $this->travelTo($grant->valid_until);
        $this->get('/admin/students?access=not_authorized')->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.access_active', false));
        $this->travelBack();
        $this->delete($base.'/grants/'.$grant->id)->assertSessionHasNoErrors()->assertRedirect($base);
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertSame(3, $person->fresh()->record_version);
    }

    public function test_inactive_or_archived_people_cannot_receive_grants_and_ids_are_scoped(): void
    {
        $inactive = $this->makePerson('students', ['status' => 'inactive']);
        $other = $this->makePerson();
        $grant = $other->authorizations()->create(['status' => 'active', 'valid_from' => now(), 'granted_by' => $this->admin->id]);
        $this->post('/admin/students/'.$inactive->id.'/grants', [...$this->schedule(), 'reason' => 'Test', 'record_version' => 1])->assertSessionHasErrors('record');
        $this->delete('/admin/students/'.$inactive->id.'/grants/'.$grant->id)->assertNotFound();
        $this->assertNull($grant->fresh()->revoked_at);
        $this->post('/admin/students/'.$other->id.'/grants', [...$this->schedule(), 'ends_at' => '2020-01-01T00:00', 'reason' => 'Test', 'record_version' => 1])->assertSessionHasErrors('ends_at');
        $this->delete('/admin/students/'.$other->id, ['record_version' => 1])->assertSessionHasNoErrors();
        $this->post('/admin/students/'.$other->id.'/grants', [...$this->schedule(), 'reason' => 'Test', 'record_version' => 2])->assertNotFound();
    }

    public function test_assignment_create_reschedule_revoke_and_guard_station_scope(): void
    {
        $guard = $this->user(['role' => 'guard']);
        $gate = Gate::create(['code' => 'TEST-A', 'name' => 'Test station A', 'status' => 'active']);
        $other = Gate::create(['code' => 'TEST-B', 'name' => 'Test station B', 'status' => 'active']);
        $data = [...$this->schedule(), 'guard_id' => $guard->id, 'gate_id' => $gate->id];
        $base = '/admin/gates/'.$gate->id.'/assignments';
        $this->post($base, $data)->assertSessionHasNoErrors()->assertRedirect('/admin/gates/'.$gate->id);
        $assignment = GuardGateAssignment::sole();
        $this->post($base, $data)->assertSessionHasErrors('starts_at');
        $this->assertSame(1, GuardGateAssignment::count());
        $this->signIn($guard)->get('/guard/scanner')->assertInertia(fn (Assert $page) => $page->has('stations', 1)->where('stations.0.id', $gate->id));
        $this->get('/guard/gates/'.$gate->id.'/scanner')->assertOk();
        $this->get('/guard/gates/'.$other->id.'/scanner')->assertForbidden();
        $this->signIn($this->admin);
        $this->put($base.'/'.$assignment->id, [...$data, 'ends_at' => now('Asia/Manila')->addHours(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->assertNotNull($assignment->fresh()->revoked_at);
        $this->assertSame(2, GuardGateAssignment::count());
        $replacement = GuardGateAssignment::latest('id')->first();
        $this->delete('/admin/guards/'.$guard->id.'/assignments/'.$replacement->id)->assertSessionHasNoErrors();
        $this->signIn($guard)->get('/guard/gates/'.$gate->id.'/scanner')->assertForbidden();
    }

    public function test_adjacent_assignments_are_allowed_but_invalid_parent_and_inactive_targets_are_rejected(): void
    {
        $guard = $this->user(['role' => 'guard']);
        $gate = Gate::create(['code' => 'TEST-A', 'name' => 'Test station A', 'status' => 'active']);
        $other = Gate::create(['code' => 'TEST-B', 'name' => 'Test station B', 'status' => 'inactive']);
        $data = [...$this->schedule(), 'guard_id' => $guard->id, 'gate_id' => $gate->id];
        $base = '/admin/gates/'.$gate->id.'/assignments';
        $this->post($base, $data)->assertSessionHasNoErrors();
        $this->post($base, [...$data, 'starts_at' => $data['ends_at'], 'ends_at' => now('Asia/Manila')->addHours(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->post('/admin/gates/'.$other->id.'/assignments', $data)->assertNotFound();
        $this->post('/admin/guards/'.$guard->id.'/assignments', [...$data, 'gate_id' => $other->id])->assertSessionHasErrors('gate_id');
        $this->post($base, [...$data, 'guard_id' => $this->admin->id])->assertSessionHasErrors('guard_id');
        $this->post($base, [...$data, 'ends_at' => $data['starts_at']])->assertSessionHasErrors('ends_at');
        $this->delete('/admin/gates/'.$other->id.'/assignments/'.GuardGateAssignment::first()->id)->assertNotFound();
        $this->assertSame(2, GuardGateAssignment::count());
    }

    public function test_rescheduling_and_revocation_roll_back_if_audit_cannot_be_written(): void
    {
        $guard = $this->user(['role' => 'guard']);
        $gate = Gate::create(['code' => 'TEST', 'name' => 'Test station', 'status' => 'active']);
        $assignment = GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subMinute()]);
        $audit = \Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('Audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->put('/admin/gates/'.$gate->id.'/assignments/'.$assignment->id, [...$this->schedule(), 'guard_id' => $guard->id, 'gate_id' => $gate->id])->assertStatus(500);
        $this->assertNull($assignment->fresh()->revoked_at);
        $this->assertSame(1, GuardGateAssignment::count());
        $this->delete('/admin/gates/'.$gate->id, ['record_version' => 1])->assertStatus(500);
        $this->assertFalse(Gate::findOrFail($gate->id)->trashed());
        $this->assertNull($assignment->fresh()->revoked_at);
    }
}
