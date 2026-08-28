<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Media;
use App\Models\Service;
use App\Models\SitemapEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('المقالات المنشورة', Article::where('status', 'published')->count())
                ->description('من '.Article::count().' مقالًا')
                ->icon('heroicon-o-document-text')
                ->color('primary'),
            Stat::make('الخدمات المنشورة', Service::where('status', 'published')->count())
                ->description('من '.Service::count().' خدمة')
                ->icon('heroicon-o-sparkles')
                ->color('success'),
            Stat::make('مكتبة الصور', Media::count())
                ->description('صور أصلية ونسخ WebP')
                ->icon('heroicon-o-photo')
                ->color('warning'),
            Stat::make('روابط خريطة الموقع', SitemapEntry::where('is_included', true)->count())
                ->description('روابط الاستعادة المحمية')
                ->icon('heroicon-o-map')
                ->color('info'),
        ];
    }
}
