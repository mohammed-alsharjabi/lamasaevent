<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContentResourceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SitemapController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('admin.login.store');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/content/{resource}', [ContentResourceController::class, 'index'])
        ->name('resources.index');
    Route::get('/content/{resource}/create', [ContentResourceController::class, 'create'])
        ->name('resources.create');
    Route::post('/content/{resource}', [ContentResourceController::class, 'store'])
        ->name('resources.store');
    Route::get('/content/{resource}/{record}/edit', [ContentResourceController::class, 'edit'])
        ->name('resources.edit');
    Route::put('/content/{resource}/{record}', [ContentResourceController::class, 'update'])
        ->name('resources.update');
    Route::delete('/content/{resource}/{record}', [ContentResourceController::class, 'destroy'])
        ->name('resources.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/sitemap', [SitemapController::class, 'index'])->name('sitemap.index');
    Route::put('/sitemap/{entry}', [SitemapController::class, 'update'])->name('sitemap.update');

    Route::get('/redirects', [RedirectController::class, 'index'])->name('redirects.index');
    Route::post('/redirects', [RedirectController::class, 'store'])->name('redirects.store');
    Route::delete('/redirects/{redirect}', [RedirectController::class, 'destroy'])
        ->name('redirects.destroy');
});
