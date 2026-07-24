<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitemapEntry extends Model
{
    protected $fillable = [
        'route_registry_id', 'loc', 'path', 'lastmod', 'changefreq', 'priority',
        'position', 'is_included',
    ];

    protected function casts(): array
    {
        return ['lastmod' => 'date', 'priority' => 'decimal:2', 'is_included' => 'boolean'];
    }

    public function routeRecord(): BelongsTo
    {
        return $this->belongsTo(RouteRecord::class, 'route_registry_id');
    }
}
