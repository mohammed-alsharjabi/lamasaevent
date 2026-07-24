<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Article;
use App\Models\Media;
use App\Models\RouteRecord;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'counts' => [
                'المقالات' => Article::count(),
                'الخدمات' => Service::count(),
                'تصنيفات الخدمات' => ServiceCategory::count(),
                'المناطق' => Area::count(),
                'الصور' => Media::count(),
                'المسارات المنشورة' => RouteRecord::where('is_published', true)->count(),
            ],
        ]);
    }
}
