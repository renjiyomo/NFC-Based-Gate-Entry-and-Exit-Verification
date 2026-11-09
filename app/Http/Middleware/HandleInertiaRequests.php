<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $preview = $request->routeIs('preview.*');
        $user = $preview ? null : $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'phase' => 11,
                'preview' => $preview,
                'simulator' => EnsureNfcSimulator::available($request),
                'hardwareLab' => EnsureHardwareWorkspace::available($request),
                'testWorkspace' => in_array(config('database.connections.mysql.database'), ['nfcheck_testing', 'nfcheck_simulator', 'nfcheck_hardware'], true),
            ],
            ...($preview ? [] : ['auth' => ['user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value, 'mustChangePassword' => $user->must_change_password] : null]]),
            'flash' => ['status' => fn () => $request->session()->get('status')],
        ];
    }
}
