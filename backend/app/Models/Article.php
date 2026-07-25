<?php

namespace App\Models;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\Concerns\HasManagedContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model implements ManagedContent
{
    use HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'hero_media_id', 'article_category_id', 'title', 'slug', 'topic', 'excerpt',
        'content_blocks', 'status', 'published_at', 'legacy_path', 'created_by',
        'updated_by', 'is_featured', 'internal_keywords', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'internal_keywords' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function routePath(): string
    {
        return "/blog/{$this->slug}";
    }
}
