<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Services\AccountService;
use Tests\ManagementTestCase;

class ManagementChoicesTest extends ManagementTestCase
{
    public function test_choices_are_paginated_searchable_and_exclude_ineligible_accounts(): void
    {
        $first = null;
        for ($i = 0; $i < 27; $i++) {
            $created = $this->user(['role' => 'guard', 'name' => sprintf('Synthetic guard %02d', $i)]);
            $first ??= $created;
        }
        $this->user(['role' => 'guard', 'status' => 'inactive', 'name' => 'Inactive guard']);
        $this->getJson('/admin/choices/guards')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('has_more', true)->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.auth_version');
        $this->getJson('/admin/choices/guards?page=2')->assertJsonCount(2, 'data')->assertJsonPath('has_more', false);
        $this->getJson('/admin/choices/guards?q=guard%2026&selected='.$first->id)->assertJsonCount(1, 'data')->assertJsonPath('selected.id', $first->id);
        $this->getJson('/admin/choices/guards?q=Inactive')->assertJsonCount(0, 'data');
        $gate = Gate::create(['code' => 'ACTIVE', 'name' => 'Active station', 'status' => 'active']);
        Gate::create(['code' => 'INACTIVE', 'name' => 'Inactive station', 'status' => 'inactive']);
        $this->getJson('/admin/choices/gates?active=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $gate->id);
        $this->getJson('/admin/choices/gates?active=0')->assertJsonCount(2, 'data');
        $this->getJson('/admin/choices/guards?q[]=bad')->assertStatus(422);
        $this->signIn($first)->getJson('/admin/choices/guards')->assertForbidden();
    }

    public function test_cli_access_changes_invalidate_stale_management_edits_and_old_assignments_can_be_revoked(): void
    {
        $guard = $this->user(['role' => 'guard'])->fresh();
        $gate = Gate::create(['code' => 'TEST', 'name' => 'Test station', 'status' => 'active']);
        $assignment = GuardGateAssignment::create(['guard_id' => $guard->id, 'gate_id' => $gate->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subMinute()]);
        app(AccountService::class)->changeAccess($guard, UserRole::Guard, RecordStatus::Inactive);
        $this->put('/admin/guards/'.$guard->id, ['name' => 'Stale update', 'status' => 'active', 'record_version' => 1])->assertSessionHasErrors('record_version');
        $this->assertSame('inactive', $guard->fresh()->status->value);
        app(AccountService::class)->changeAccess($guard, UserRole::Admin, RecordStatus::Active);
        $this->delete('/admin/gates/'.$gate->id.'/assignments/'.$assignment->id)->assertSessionHasNoErrors();
        $this->assertNotNull($assignment->fresh()->revoked_at);
    }
}
