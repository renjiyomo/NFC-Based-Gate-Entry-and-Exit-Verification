<?php

namespace App\Http\Controllers;

use App\Models\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController
{
    public function admin(string $page = 'dashboard'): Response
    {
        abort_unless(in_array($page, ['dashboard', 'students', 'employees', 'credentials', 'guards', 'gates', 'history', 'reports', 'analytics', 'settings'], true), 404);

        return Inertia::render('Workspace', ['page' => $page]);
    }

    public function guard(): Response
    {
        return Inertia::render('Workspace', ['page' => 'scanner', 'stations' => $this->stations()]);
    }

    public function station(Gate $gate): Response
    {
        return Inertia::render('Workspace', ['page' => 'scanner', 'stations' => $this->stations(), 'station' => ['id' => $gate->id, 'name' => $gate->name, 'code' => $gate->code]]);
    }

    public function recent(): Response
    {
        return Inertia::render('Workspace', ['page' => 'history']);
    }

    private function stations(): array
    {
        return Gate::where('status', 'active')->whereHas('assignments', fn ($query) => $query->where('guard_id', request()->user()->id)->effective())->orderBy('name')->limit(500)->get(['id', 'name', 'code'])->toArray();
    }
}
