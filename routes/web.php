<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureLocalPreview;
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
    Route::get('/admin/dashboard', [WorkspaceController::class, 'admin'])->middleware('can:admin.workspace')->name('admin.dashboard');
    Route::get('/admin/{page}', [WorkspaceController::class, 'admin'])->middleware('can:admin.workspace')->name('admin.workspace');
    Route::get('/guard/scanner', [WorkspaceController::class, 'guard'])->middleware('can:guard.workspace')->name('guard.scanner');
    Route::get('/guard/activity', [WorkspaceController::class, 'recent'])->middleware('can:guard.workspace')->name('guard.activity');
    Route::get('/guard/gates/{gate}/scanner', [WorkspaceController::class, 'station'])->middleware('can:operate,gate')->name('guard.station');
});
Route::prefix('preview')->name('preview.')->middleware(EnsureLocalPreview::class)->group(function (): void {
    Route::get('/login', [PreviewController::class, 'login'])->name('login');
    Route::get('/{page?}', [PreviewController::class, 'workspace'])->name('workspace');
});
