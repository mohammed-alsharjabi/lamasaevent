<?php

use App\Http\Controllers\Admin\PreviewServiceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/admin/service-previews/{service}', PreviewServiceController::class)
    ->middleware('auth')
    ->name('admin.services.preview');
