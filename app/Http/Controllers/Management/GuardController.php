<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\ManagementRequest;
use App\Models\User;
use App\Support\DirectoryData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuardController
{
    public function index(Request $request): Response
    {
        Gate::authorize('admin.workspace');
        $filters = DirectoryData::filters($request);
        $query = DirectoryData::records(User::where('role', 'guard')->whereNotNull('gate_id'), $filters);
        if ($filters['q'] !== '') {
            $like = DirectoryData::like($filters['q']);
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("username LIKE ? ESCAPE '!'", [$like]));
        }

        return Inertia::render('management/GuardsIndex', ['filters' => $filters, 'records' => $query->orderBy('name')->paginate((int) $filters['per_page'])->appends($filters)->through(fn ($u) => DirectoryData::guard($u))]);
    }

    public function create(): RedirectResponse
    {
        Gate::authorize('admin.workspace');

        return redirect('/admin/gates/create');
    }

    public function store(ManagementRequest $request): RedirectResponse
    {
        abort(410, 'Create the account together with its gate.');
    }

    public function show(User $guard): RedirectResponse
    {
        Gate::authorize('admin.workspace');

        return redirect($guard->gate_id ? '/admin/gates/'.$guard->gate_id : '/admin/guards');
    }

    public function edit(User $guard): RedirectResponse
    {
        Gate::authorize('admin.workspace');

        return redirect($guard->gate_id ? '/admin/gates/'.$guard->gate_id.'/edit' : '/admin/guards');
    }

    public function update(ManagementRequest $request, User $guard): RedirectResponse
    {
        abort(410, 'Edit the gate and account together.');
    }

    public function destroy(ManagementRequest $request, User $guard): RedirectResponse
    {
        abort(410, 'Deactivate the gate account from its gate settings.');
    }

    public function restore(ManagementRequest $request, User $guard): RedirectResponse
    {
        abort(410, 'Manage this account from its gate settings.');
    }

    public function setup(ManagementRequest $request, User $guard): RedirectResponse
    {
        abort(410, 'Set the password directly in gate settings. Email activation is retired.');
    }
}
