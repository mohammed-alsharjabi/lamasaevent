<?php

use App\Http\Controllers\Api\ContentExportController;
use App\Http\Controllers\Api\PublishedContentController;
use App\Http\Controllers\Api\RedirectLookupController;
use App\Http\Controllers\Api\SitemapXmlController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('/content-export', ContentExportController::class);
    Route::get('/content/{type}', [PublishedContentController::class, 'index']);
    Route::get('/content/{type}/{id}', [PublishedContentController::class, 'show'])
        ->whereNumber('id');
    Route::get('/sitemap.xml', SitemapXmlController::class);
    Route::get('/redirect', RedirectLookupController::class);
});
