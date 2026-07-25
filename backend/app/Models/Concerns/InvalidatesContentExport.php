<?php

namespace App\Models\Concerns;

use App\Services\PublishPipeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

trait InvalidatesContentExport
{
    protected static function bootInvalidatesContentExport(): void
    {
        static::saved(function (Model $model): void {
            Cache::forget('content-export:v2');

            if (! app()->runningInConsole() && auth()->check()) {
                app(PublishPipeline::class)->queue($model, auth()->id());
            }
        });
        static::deleted(fn () => Cache::forget('content-export:v2'));
    }
}
