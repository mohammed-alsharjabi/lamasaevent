<?php

namespace App\Models;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\Concerns\HasManagedContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model implements ManagedContent
{
    use HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'hero_media_id', 'title', 'slug', 'summary', 'content_blocks', 'status',
        'published_at', 'legacy_path', 'sort_order', 'created_by', 'updated_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function routePath(): string
    {
        return "/areas/{$this->slug}";
    }
}
