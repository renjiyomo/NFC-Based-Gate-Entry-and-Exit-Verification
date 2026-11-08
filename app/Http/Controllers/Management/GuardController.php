<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\ManagementRequest;
use App\Models\User;
use App\Services\GuardManagement;
use App\Support\DirectoryData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate as Policy;
use Inertia\Inertia;
use Inertia\Response;

class GuardController
{
    public function index(Request $request): Response
    {
        Policy::authorize('viewAny', User::class);
        $filters = DirectoryData::filters($request);
        $query = DirectoryData::records(User::where('role', 'guard'), $filters)->withCount(['guardAssignments' => fn ($q) => $q->effective()]);
        if ($filters['q'] !== '') {
            $like = DirectoryData::like($filters['q']);
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]));
        }
        if ($filters['gate_id']) {
            $query->whereHas('guardAssignments', fn ($q) => $q->where('gate_id', $filters['gate_id'])->effective());
        }

        return Inertia::render('management/GuardsIndex', ['filters' => $filters,
            'records' => $query->orderBy('name')->orderBy('id')->paginate((int) $filters['per_page'])->appends($filters)->through(fn (User $user) => DirectoryData::guard($user))]);
    }

    public function create(): Response
    {
        Policy::authorize('create', User::class);

        return Inertia::render('management/GuardForm', ['record' => null]);
    }

    public function store(ManagementRequest $request, GuardManagement $guards): RedirectResponse
    {
        $user = $request->run(fn () => $guards->create($request->user(), $request->validated()));
        $sent = $guards->setupLink($request->user(), $user);

        return redirect('/admin/guards/'.$user->id)->with('status', $sent ? 'Guard account created. A password setup link was sent to the account email.' : 'Guard account created. The setup email could not be delivered; check mail service and resend the link.');
    }

    public function show(User $guard): Response
    {
        Policy::authorize('manageGuard', $guard);

        return Inertia::render('management/GuardShow', ['record' => DirectoryData::guard($guard), 'assignments' => AssignmentController::list($guard->guardAssignments())]);
    }

    public function edit(User $guard): Response
    {
        Policy::authorize('manageGuard', $guard);
        abort_if($guard->trashed(), 404);

        return Inertia::render('management/GuardForm', ['record' => DirectoryData::guard($guard)]);
    }

    public function update(ManagementRequest $request, User $guard, GuardManagement $guards): RedirectResponse
    {
        Policy::authorize('manageGuard', $guard);
        $request->run(fn () => $guards->update($request->user(), $guard, $request->validated()));

        return redirect('/admin/guards/'.$guard->id)->with('status', 'Guard account updated. Disabled accounts cannot operate a station.');
    }

    public function destroy(ManagementRequest $request, User $guard, GuardManagement $guards): RedirectResponse
    {
        Policy::authorize('manageGuard', $guard);
        $request->run(fn () => $guards->update($request->user(), $guard, $request->validated(), false));

        return redirect('/admin/guards')->with('status', 'Guard account archived. Sessions and assignments were revoked.');
    }

    public function restore(ManagementRequest $request, User $guard, GuardManagement $guards): RedirectResponse
    {
        Policy::authorize('manageGuard', $guard);
        $request->run(fn () => $guards->update($request->user(), $guard, $request->validated(), true));

        return redirect('/admin/guards/'.$guard->id)->with('status', 'Guard restored as inactive. Review and activate the account before assigning a gate.');
    }

    public function setup(ManagementRequest $request, User $guard, GuardManagement $guards): RedirectResponse
    {
        Policy::authorize('manageGuard', $guard);
        $sent = $guards->setupLink($request->user(), $guard);

        return redirect('/admin/guards/'.$guard->id)->with('status', $sent ? 'A password setup link was requested. Check the account email; recent links are not sent again within one minute.' : 'The setup email could not be delivered. Check the mail service and try again.');
    }
}
