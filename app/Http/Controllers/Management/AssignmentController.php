<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\ManagementRequest;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\User;
use App\Services\AccessManagement;
use App\Support\DirectoryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;

class AssignmentController
{
    public function gateStore(ManagementRequest $request, Gate $gate, AccessManagement $access): RedirectResponse
    {
        return $this->store($request, $access);
    }

    public function guardStore(ManagementRequest $request, User $guard, AccessManagement $access): RedirectResponse
    {
        return $this->store($request, $access);
    }

    public function gateUpdate(ManagementRequest $request, Gate $gate, GuardGateAssignment $assignment, AccessManagement $access): RedirectResponse
    {
        return $this->update($request, $access);
    }

    public function guardUpdate(ManagementRequest $request, User $guard, GuardGateAssignment $assignment, AccessManagement $access): RedirectResponse
    {
        return $this->update($request, $access);
    }

    public function gateDestroy(ManagementRequest $request, Gate $gate, GuardGateAssignment $assignment, AccessManagement $access): RedirectResponse
    {
        return $this->destroy($request, $access);
    }

    public function guardDestroy(ManagementRequest $request, User $guard, GuardGateAssignment $assignment, AccessManagement $access): RedirectResponse
    {
        return $this->destroy($request, $access);
    }

    public static function list(HasMany $query): LengthAwarePaginator
    {
        return $query->with(['guardUser', 'gate'])->latest('id')->paginate(15)->through(fn (GuardGateAssignment $assignment) => [
            'id' => $assignment->id, 'guard_id' => $assignment->guard_id, 'guard_name' => $assignment->guardUser->name,
            'gate_id' => $assignment->gate_id, 'gate_name' => $assignment->gate->name, 'gate_code' => $assignment->gate->code,
            'starts_at' => $assignment->starts_at->toIso8601String(), 'ends_at' => $assignment->ends_at?->toIso8601String(), 'revoked_at' => $assignment->revoked_at?->toIso8601String(),
            'state' => DirectoryData::intervalState($assignment->starts_at, $assignment->ends_at, $assignment->revoked_at),
            'operable' => $assignment->guardUser->isGuard() && ! $assignment->gate->trashed() && $assignment->gate->status->value === 'active']);
    }

    public function store(ManagementRequest $request, AccessManagement $access): RedirectResponse
    {
        abort(410, 'Scheduled assignments and separate access grants are retired.');
    }

    public function update(ManagementRequest $request, AccessManagement $access): RedirectResponse
    {
        abort(410, 'Scheduled assignments and separate access grants are retired.');
    }

    public function destroy(ManagementRequest $request, AccessManagement $access): RedirectResponse
    {
        abort(410, 'Scheduled assignments and separate access grants are retired.');
    }
}
