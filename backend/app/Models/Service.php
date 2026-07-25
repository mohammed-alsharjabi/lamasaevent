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

class Service extends Model implements ManagedContent
{
    use GeneratesInternalSlug, HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'service_category_id', 'parent_id', 'hero_media_id', 'title', 'slug', 'excerpt',
        'content_blocks', 'status', 'published_at', 'legacy_path', 'sort_order',
        'created_by', 'updated_by', 'is_featured', 'cta_label', 'cta_url',
        'whatsapp_enabled',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'whatsapp_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            $parentId = $service->getAttribute('parent_id');

            if (! $parentId) {
                return;
            }

            if ($service->exists && (int) $parentId === (int) $service->getKey()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'لا يمكن أن تكون الخدمة تابعة لنفسها.',
                ]);
            }

            $parent = static::query()->find($parentId);

            if (! $parent || $parent->getAttribute('parent_id')) {
                throw ValidationException::withMessages([
                    'parent_id' => 'يجب اختيار خدمة رئيسية صالحة.',
                ]);
            }

            if ($service->exists && $service->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'لا يمكن تحويل خدمة لديها خدمات فرعية إلى خدمة فرعية.',
                ]);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Service::class, 'parent_id')->orderBy('sort_order');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function routePath(): string
    {
        return "/services/{$this->slug}";
    }
}
