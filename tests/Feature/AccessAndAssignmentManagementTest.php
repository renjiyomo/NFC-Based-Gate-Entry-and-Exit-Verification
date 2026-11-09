<?php

namespace Tests\Feature;

use App\Models\AccessAuthorization;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use Tests\ManagementTestCase;

class AccessAndAssignmentManagementTest extends ManagementTestCase
{
    public function test_separate_grants_and_dated_assignments_are_retired_without_writes(): void
    {
        $person = $this->makePerson();
        $gate = Gate::create(['code' => 'RETIRE', 'name' => 'Synthetic gate', 'status' => 'active']);
        $guard = $this->user(['role' => 'guard']);
        $this->post('/admin/students/'.$person->id.'/grants', [...$this->schedule(), 'reason' => 'Synthetic', 'record_version' => 1])->assertStatus(410);
        $this->post('/admin/gates/'.$gate->id.'/assignments', [...$this->schedule(), 'guard_id' => $guard->id, 'gate_id' => $gate->id])->assertStatus(410);
        $this->assertSame(0, AccessAuthorization::count());
        $this->assertSame(0, GuardGateAssignment::count());
    }

    public function test_retiring_the_ui_does_not_delete_legacy_grants_or_assignments(): void
    {
        $person = $this->makePerson();
        $gate = Gate::create(['code' => 'HISTORY', 'name' => 'Synthetic historical gate', 'status' => 'active']);
        $guard = $this->user(['role' => 'guard']);
        $grant = $person->authorizations()->create(['status' => 'active', 'valid_from' => now()->subMinute(), 'granted_by' => $this->admin->id]);
        $assignment = GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subMinute()]);
        $this->get('/admin/students/'.$person->id)->assertOk();
        $this->get('/admin/gates/'.$gate->id)->assertOk();
        $this->assertNotNull($grant->fresh());
        $this->assertNotNull($assignment->fresh());
    }
}
