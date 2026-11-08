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
        return Inertia::render('Workspace', ['page' => 'scanner']);
    }

    public function station(Gate $gate): Response
    {
        return Inertia::render('Workspace', ['page' => 'scanner', 'station' => ['id' => $gate->id, 'name' => $gate->name, 'code' => $gate->code]]);
    }

    public function recent(): Response
    {
        return Inertia::render('Workspace', ['page' => 'history']);
    }
}
