<?php

use App\Http\Controllers\PreviewController;
use App\Http\Middleware\EnsureLocalPreview;
use Illuminate\Support\Facades\Route;

// UI previews only. Authentication, policies and operational routes start in Phase 2.
Route::middleware(EnsureLocalPreview::class)->group(function (): void {
    Route::redirect('/', '/login');
    Route::get('/login', [PreviewController::class, 'login'])->name('login');
    Route::get('/preview/{page?}', [PreviewController::class, 'workspace'])->name('preview');
});
