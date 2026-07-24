<?php

use App\Http\Controllers\Api\ContentExportController;
use App\Http\Controllers\Api\RedirectLookupController;
use App\Http\Controllers\Api\SitemapXmlController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/content-export', ContentExportController::class);
    Route::get('/sitemap.xml', SitemapXmlController::class);
    Route::get('/redirect', RedirectLookupController::class);
});
