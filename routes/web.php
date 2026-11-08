<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Credentials\CredentialController;
use App\Http\Controllers\GateOperations\ActivityController;
use App\Http\Controllers\GateOperations\OperationController;
use App\Http\Controllers\Management\AssignmentController;
use App\Http\Controllers\Management\AuthorizationController;
use App\Http\Controllers\Management\ChoiceController;
use App\Http\Controllers\Management\GateController;
use App\Http\Controllers\Management\GuardController;
use App\Http\Controllers\Management\PersonController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureLocalPreview;
use App\Http\Middleware\EnsureNfcSimulator;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => redirect()->route($request->user() ? ($request->user()->isAdmin() ? 'admin.dashboard' : 'guard.scanner') : 'login'));
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login-ingress');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:recovery')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:reset')->name('password.update');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->middleware('throttle:password-change')->name('password.change');
});
Route::middleware(['auth', 'auth.session', EnsurePasswordChanged::class])->group(function (): void {
    foreach (['guard/gates' => 'operations.', 'simulator/guard/gates' => 'lab.'] as $prefix => $name) {
        Route::prefix($prefix)->middleware($name === 'lab.' ? [EnsureNfcSimulator::class, 'can:guard.workspace', 'can:operate,gate'] : ['can:guard.workspace', 'can:operate,gate'])->name($name)->group(function (): void {
            Route::post('/{gate}/scan', [OperationController::class, 'scan'])->whereNumber('gate')->middleware('throttle:gate-write')->name('scan');
            Route::get('/{gate}/requests/{requestId}', [OperationController::class, 'recover'])->whereNumber('gate')->whereUuid('requestId')->middleware('throttle:gate-read')->name('recover');
            Route::post('/{gate}/attempts/{attempt}/review', [OperationController::class, 'review'])->whereNumber('gate')->whereUuid('attempt')->middleware('throttle:gate-write')->name('review');
            Route::get('/{gate}/attempts/{attempt}/photo', [OperationController::class, 'photo'])->whereNumber('gate')->whereUuid('attempt')->middleware('throttle:gate-read')->name('photo');
        });
    }
    Route::get('/admin/history', [ActivityController::class, 'index'])->middleware('can:admin.workspace')->name('operations.history');
    Route::get('/admin/history/feed', [ActivityController::class, 'feed'])->middleware(['can:admin.workspace', 'throttle:gate-read'])->name('operations.history.feed');
    Route::get('/guard/activity/feed', [ActivityController::class, 'feed'])->middleware(['can:guard.workspace', 'throttle:gate-read'])->name('operations.activity.feed');
    Route::get('/guard/gates/{gate}/activity', [ActivityController::class, 'feed'])->whereNumber('gate')->middleware(['can:guard.workspace', 'can:operate,gate', 'throttle:gate-read'])->name('operations.station.activity');
    Route::prefix('admin/credentials')->middleware('can:admin.workspace')->name('credentials.')->group(function (): void {
        Route::get('/', [CredentialController::class, 'index'])->name('index');
        Route::get('/create', [CredentialController::class, 'create'])->name('create');
        Route::get('/people', [CredentialController::class, 'people'])->name('people');
        Route::post('/issue', [CredentialController::class, 'issue'])->middleware('throttle:credential-write')->name('issue');
        Route::post('/check', [CredentialController::class, 'check'])->middleware('throttle:credential-check')->name('check');
        Route::get('/{credential}', [CredentialController::class, 'show'])->whereUuid('credential')->name('show');
        Route::post('/{credential}/read-back', [CredentialController::class, 'readBack'])->middleware('throttle:credential-write')->whereUuid('credential')->name('read-back');
        Route::post('/{credential}/activate', [CredentialController::class, 'activate'])->middleware('throttle:credential-write')->whereUuid('credential')->name('activate');
        Route::post('/{credential}/cancel', [CredentialController::class, 'cancel'])->middleware('throttle:credential-write')->whereUuid('credential')->name('cancel');
        Route::post('/{credential}/revoke', [CredentialController::class, 'revoke'])->middleware('throttle:credential-write')->whereUuid('credential')->name('revoke');
    });
    Route::prefix('simulator')->middleware([EnsureNfcSimulator::class, 'can:admin.workspace'])->name('simulator.')->group(function (): void {
        Route::get('/', [CredentialController::class, 'simulator'])->name('index');
        Route::get('/issue', [CredentialController::class, 'create'])->name('create');
        Route::post('/issue', [CredentialController::class, 'issue'])->middleware('throttle:credential-write')->name('issue');
        Route::post('/check', [CredentialController::class, 'check'])->middleware('throttle:credential-check')->name('check');
        Route::post('/credentials/{credential}/read-back', [CredentialController::class, 'readBack'])->middleware('throttle:credential-write')->whereUuid('credential')->name('read-back');
        Route::post('/credentials/{credential}/activate', [CredentialController::class, 'activate'])->middleware('throttle:credential-write')->whereUuid('credential')->name('activate');
    });
    Route::prefix('admin')->middleware('can:admin.workspace')->name('admin.')->group(function (): void {
        Route::get('/choices/{kind}', ChoiceController::class)->whereIn('kind', ['gates', 'guards'])->name('choices');
        Route::get('/{directory}', [PersonController::class, 'index'])->whereIn('directory', ['students', 'employees'])->name('people.index');
        Route::get('/{directory}/create', [PersonController::class, 'create'])->whereIn('directory', ['students', 'employees'])->name('people.create');
        Route::post('/{directory}', [PersonController::class, 'store'])->whereIn('directory', ['students', 'employees'])->middleware('throttle:management')->name('people.store');
        Route::get('/{directory}/{person}/photo', [PersonController::class, 'photo'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->withTrashed()->name('people.photo');
        Route::get('/{directory}/{person}/edit', [PersonController::class, 'edit'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->withTrashed()->name('people.edit');
        Route::get('/{directory}/{person}', [PersonController::class, 'show'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->withTrashed()->name('people.show');
        Route::match(['put', 'patch'], '/{directory}/{person}', [PersonController::class, 'update'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->withTrashed()->middleware('throttle:management')->name('people.update');
        Route::delete('/{directory}/{person}', [PersonController::class, 'destroy'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->withTrashed()->middleware('throttle:management')->name('people.destroy');
        Route::post('/{directory}/{person}/restore', [PersonController::class, 'restore'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->withTrashed()->middleware('throttle:management')->name('people.restore');
        Route::post('/{directory}/{person}/grants', [AuthorizationController::class, 'store'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->middleware('throttle:management')->name('people.grants.store');
        Route::delete('/{directory}/{person}/grants/{grant}', [AuthorizationController::class, 'destroy'])->whereIn('directory', ['students', 'employees'])->whereNumber('person')->withTrashed()->middleware('throttle:management')->name('people.grants.destroy');

        Route::resource('gates', GateController::class)->withTrashed()->middlewareFor(['store', 'update', 'destroy'], 'throttle:management');
        Route::post('/gates/{gate}/restore', [GateController::class, 'restore'])->withTrashed()->middleware('throttle:management')->name('gates.restore');
        Route::resource('guards', GuardController::class)->parameters(['guards' => 'guard'])->withTrashed()->middlewareFor(['store', 'update', 'destroy'], 'throttle:management');
        Route::post('/guards/{guard}/restore', [GuardController::class, 'restore'])->withTrashed()->middleware('throttle:management')->name('guards.restore');
        Route::post('/guards/{guard}/setup-link', [GuardController::class, 'setup'])->middleware('throttle:guard-setup')->name('guards.setup');
        Route::post('/gates/{gate}/assignments', [AssignmentController::class, 'gateStore'])->middleware('throttle:management')->name('assignments.store');
        Route::post('/guards/{guard}/assignments', [AssignmentController::class, 'guardStore'])->middleware('throttle:management')->name('guards.assignments.store');
        Route::put('/gates/{gate}/assignments/{assignment}', [AssignmentController::class, 'gateUpdate'])->middleware('throttle:management')->name('assignments.update');
        Route::put('/guards/{guard}/assignments/{assignment}', [AssignmentController::class, 'guardUpdate'])->middleware('throttle:management')->name('guards.assignments.update');
        Route::delete('/gates/{gate}/assignments/{assignment}', [AssignmentController::class, 'gateDestroy'])->withTrashed()->middleware('throttle:management')->name('assignments.destroy');
        Route::delete('/guards/{guard}/assignments/{assignment}', [AssignmentController::class, 'guardDestroy'])->withTrashed()->middleware('throttle:management')->name('guards.assignments.destroy');
    });
    Route::get('/admin/dashboard', [WorkspaceController::class, 'admin'])->middleware('can:admin.workspace')->name('admin.dashboard');
    Route::get('/admin/{page}', [WorkspaceController::class, 'admin'])->middleware('can:admin.workspace')->name('admin.workspace');
    Route::get('/guard/scanner', [ActivityController::class, 'scanner'])->middleware('can:guard.workspace')->name('guard.scanner');
    Route::get('/guard/activity', [ActivityController::class, 'index'])->middleware('can:guard.workspace')->name('guard.activity');
    Route::get('/guard/gates/{gate}/scanner', [ActivityController::class, 'scanner'])->middleware(['can:guard.workspace', 'can:operate,gate'])->name('guard.station');
});
Route::prefix('preview')->name('preview.')->middleware(EnsureLocalPreview::class)->group(function (): void {
    Route::get('/login', [PreviewController::class, 'login'])->name('login');
    Route::get('/{page?}', [PreviewController::class, 'workspace'])->name('workspace');
});
