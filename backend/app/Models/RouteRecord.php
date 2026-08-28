<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class RouteRecord extends Model
{
    protected $table = 'route_registry';

    protected $fillable = [
        'path', 'exact_url', 'is_legacy', 'slug_locked', 'is_published',
        'legacy_html_sha256', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_legacy' => 'boolean',
            'slug_locked' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $route): void {
            if (! $route->getRawOriginal('is_legacy')) {
                return;
            }

            if (
                $route->isDirty(['path', 'exact_url'])
                || (
                    $route->isDirty('is_published')
                    && ! $route->getAttribute('is_published')
                )
            ) {
                throw ValidationException::withMessages([
                    'route' => 'لا يمكن تغيير أو إلغاء نشر مسار موروث ومحمي.',
                ]);
            }
        });

        static::deleting(function (self $route): void {
            if ($route->getAttribute('is_legacy')) {
                throw ValidationException::withMessages([
                    'route' => 'لا يمكن حذف مسار موروث ومحمي.',
                ]);
            }
        });
    }

    public function routable(): MorphTo
    {
        return $this->morphTo();
    }
}
