<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasManagedContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'service_category_id', 'hero_media_id', 'title', 'slug', 'excerpt',
        'content_blocks', 'status', 'published_at', 'legacy_path', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function routePath(): string
    {
        return "/services/{$this->slug}";
    }
}
