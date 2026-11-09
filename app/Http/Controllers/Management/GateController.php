<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\ManagementRequest;
use App\Models\Gate;
use App\Services\GateManagement;
use App\Support\DirectoryData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate as Policy;
use Inertia\Inertia;
use Inertia\Response;

class GateController
{
    public function index(Request $request): Response
    {
        Policy::authorize('viewAny', Gate::class);
        $filters = DirectoryData::filters($request);
        $query = DirectoryData::records(Gate::query(), $filters)->with('account');
        if ($filters['q'] !== '') {
            $like = DirectoryData::like($filters['q']);
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("code LIKE ? ESCAPE '!'", [$like]));
        }

        return Inertia::render('management/GatesIndex', ['filters' => $filters, 'records' => $query->orderBy('name')->orderBy('id')->paginate((int) $filters['per_page'])->appends($filters)->through(fn (Gate $gate) => DirectoryData::gate($gate))]);
    }

    public function create(): Response
    {
        Policy::authorize('create', Gate::class);

        return Inertia::render('management/GateForm', ['record' => null]);
    }

    public function store(ManagementRequest $request, GateManagement $gates): RedirectResponse
    {
        $gate = $request->run(fn () => $gates->save($request->user(), $request->validated()));

        return redirect('/admin/gates/'.$gate->id)->with('status', 'Gate station created.');
    }

    public function show(Gate $gate): Response
    {
        Policy::authorize('view', $gate);

        return Inertia::render('management/GateShow', ['record' => DirectoryData::gate($gate)]);
    }

    public function edit(Gate $gate): Response
    {
        Policy::authorize('update', $gate);
        abort_if($gate->trashed(), 404);

        return Inertia::render('management/GateForm', ['record' => DirectoryData::gate($gate)]);
    }

    public function update(ManagementRequest $request, Gate $gate, GateManagement $gates): RedirectResponse
    {
        Policy::authorize('update', $gate);
        $request->run(fn () => $gates->save($request->user(), $request->validated(), $gate));

        return redirect('/admin/gates/'.$gate->id)->with('status', 'Gate station updated.');
    }

    public function destroy(ManagementRequest $request, Gate $gate, GateManagement $gates): RedirectResponse
    {
        Policy::authorize('archive', $gate);
        $request->run(fn () => $gates->archive($request->user(), $gate, $request->integer('record_version')));

        return redirect('/admin/gates')->with('status', 'Gate archived and its login disabled. History is retained.');
    }

    public function restore(ManagementRequest $request, Gate $gate, GateManagement $gates): RedirectResponse
    {
        Policy::authorize('archive', $gate);
        $request->run(fn () => $gates->archive($request->user(), $gate, $request->integer('record_version'), true));

        return redirect('/admin/gates/'.$gate->id)->with('status', 'Gate restored as inactive. Edit the gate to enable its login.');
    }
}
