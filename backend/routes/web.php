<?php

use App\Http\Controllers\Admin\PreviewServiceController;
use App\Http\Controllers\Api\SitemapXmlController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/service-previews/{service}', PreviewServiceController::class)
    ->middleware('auth')
    ->name('admin.services.preview');

Route::get('/sitemap.xml', SitemapXmlController::class)
    ->name('public.sitemap');

Route::get('/', PublicPageController::class)->name('public.home');
Route::get('/services', PublicPageController::class);
Route::get('/services/{path}', PublicPageController::class)->where('path', '.*');
Route::get('/blog', PublicPageController::class);
Route::get('/blog/{path}', PublicPageController::class)->where('path', '.*');
Route::get('/areas', PublicPageController::class);
Route::get('/areas/{path}', PublicPageController::class)->where('path', '.*');
Route::get('/gallery', PublicPageController::class);
Route::get('/about', PublicPageController::class);
Route::get('/contact', PublicPageController::class);
