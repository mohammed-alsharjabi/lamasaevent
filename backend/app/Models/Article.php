<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasManagedContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'hero_media_id', 'title', 'slug', 'topic', 'excerpt', 'content_blocks',
        'status', 'published_at', 'legacy_path',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function routePath(): string
    {
        return "/blog/{$this->slug}";
    }
}
