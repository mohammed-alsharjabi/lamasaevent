<?php

namespace App\Models;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\Concerns\GeneratesInternalSlug;
use App\Models\Concerns\HasManagedContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ServiceCategory extends Model implements ManagedContent
{
    use GeneratesInternalSlug, HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'summary', 'content_blocks', 'status', 'published_at',
        'legacy_path', 'sort_order', 'hero_media_id', 'created_by', 'updated_by',
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

    protected static function booted(): void
    {
        static::deleting(function (self $category): void {
            if ($category->services()->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'انقل الخدمات المرتبطة قبل حذف التصنيف.',
                ]);
            }
        });
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function routePath(): string
    {
        return "/services/category/{$this->slug}/";
    }
}
