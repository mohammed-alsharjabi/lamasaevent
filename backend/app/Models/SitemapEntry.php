<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesContentExport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SitemapEntry extends Model
{
    use InvalidatesContentExport;

    protected $fillable = [
        'route_registry_id', 'loc', 'path', 'lastmod', 'changefreq', 'priority',
        'position', 'is_included',
    ];

    protected function casts(): array
    {
        return ['lastmod' => 'date', 'priority' => 'decimal:2', 'is_included' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            $route = $entry->routeRecord()->first();

            if (
                ! $entry->exists
                || ! $route?->getAttribute('is_legacy')
            ) {
                return;
            }

            if (
                $entry->isDirty(['loc', 'path'])
                || (
                    $entry->isDirty('is_included')
                    && ! $entry->getAttribute('is_included')
                )
            ) {
                throw ValidationException::withMessages([
                    'sitemap' => 'لا يمكن تغيير رابط موروث أو استبعاده من sitemap.',
                ]);
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->routeRecord()->where('is_legacy', true)->exists()) {
                throw ValidationException::withMessages([
                    'sitemap' => 'لا يمكن حذف رابط موروث من sitemap.',
                ]);
            }
        });
    }

    public function routeRecord(): BelongsTo
    {
        return $this->belongsTo(RouteRecord::class, 'route_registry_id');
    }
}
