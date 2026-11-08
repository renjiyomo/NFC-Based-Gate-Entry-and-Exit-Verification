<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PreviewController
{
    public function login(): Response
    {
        return Inertia::render('Login');
    }

    public function workspace(string $page = 'dashboard'): Response
    {
        abort_unless(in_array($page, [
            'dashboard', 'students', 'employees', 'credentials', 'guards', 'gates',
            'scanner', 'history', 'reports', 'analytics', 'settings',
        ], true), 404);

        return Inertia::render('Workspace', ['page' => $page]);
    }
}
