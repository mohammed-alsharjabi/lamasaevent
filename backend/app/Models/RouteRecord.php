<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    public function routable(): MorphTo
    {
        return $this->morphTo();
    }
}
