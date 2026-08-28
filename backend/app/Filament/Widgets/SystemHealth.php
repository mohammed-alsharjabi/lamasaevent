<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealth extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $databaseHealthy = $this->databaseHealthy();
        $storageHealthy = is_writable(Storage::disk(config('media.disk'))->path(''));
        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : 0;

        return [
            Stat::make('قاعدة البيانات', $databaseHealthy ? 'متصلة' : 'غير متاحة')
                ->icon('heroicon-o-circle-stack')
                ->color($databaseHealthy ? 'success' : 'danger'),
            Stat::make('التخزين', $storageHealthy ? 'قابل للكتابة' : 'مقفل')
                ->icon('heroicon-o-server-stack')
                ->color($storageHealthy ? 'success' : 'danger'),
            Stat::make('مهام فاشلة', $failedJobs)
                ->icon('heroicon-o-queue-list')
                ->color($failedJobs === 0 ? 'success' : 'danger'),
        ];
    }

    private function databaseHealthy(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
