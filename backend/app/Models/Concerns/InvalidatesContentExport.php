<?php

namespace App\Models\Concerns;

use App\Services\ContentExportService;
use App\Services\PublishPipeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

trait InvalidatesContentExport
{
    protected static function bootInvalidatesContentExport(): void
    {
        static::saved(fn (Model $model) => self::invalidateContentExport($model));
        static::deleted(fn (Model $model) => self::invalidateContentExport($model));
        static::registerModelEvent(
            'restored',
            fn (Model $model) => self::invalidateContentExport($model),
        );
    }

    private static function invalidateContentExport(Model $model): void
    {
        Cache::forget(ContentExportService::CACHE_KEY);
        Cache::forget('sitemap-xml:v1');

        if (auth()->check()) {
            app(PublishPipeline::class)->queue($model, auth()->id());
        }
    }
}
