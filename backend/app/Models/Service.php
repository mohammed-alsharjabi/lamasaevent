<?php

namespace App\Models;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\Concerns\GeneratesInternalSlug;
use App\Models\Concerns\HasManagedContent;
use App\Services\ServiceDefaultsService;
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
        'whatsapp_enabled', 'seo_overrides', 'cta_overrides',
        'uses_generated_defaults', 'seo_title_override',
        'meta_description_override', 'slug_override', 'canonical_override',
        'robots_override', 'og_title_override', 'og_description_override',
        'og_image_override', 'schema_override', 'target_search_phrase',
        'hero_alt_override',
    ];

    protected $hidden = [
        'seo_overrides', 'cta_overrides', 'uses_generated_defaults',
        'seo_title_override', 'meta_description_override', 'slug_override',
        'canonical_override', 'robots_override', 'og_title_override',
        'og_description_override', 'og_image_override', 'schema_override',
        'target_search_phrase', 'hero_alt_override',
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
            'seo_overrides' => 'array',
            'cta_overrides' => 'array',
            'schema_override' => 'array',
            'uses_generated_defaults' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            if (! array_key_exists('uses_generated_defaults', $service->getAttributes())) {
                $service->uses_generated_defaults = blank($service->legacy_path);
            }
        });

        static::saving(fn (Service $service) => app(ServiceDefaultsService::class)
            ->apply($service));

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

        static::deleting(function (Service $service): void {
            if ($service->children()->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'انقل أو احذف الخدمات الفرعية قبل حذف الخدمة الرئيسية.',
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
        return $this->hasMany(Service::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    /** @return array<string, mixed> */
    public function effectiveSeoOverrides(): array
    {
        $storedOverrides = $this->getAttribute('seo_overrides');
        $overrides = is_array($storedOverrides) ? $storedOverrides : [];
        $openGraph = is_array($overrides['open_graph'] ?? null)
            ? $overrides['open_graph']
            : [];

        foreach ([
            'title' => 'seo_title_override',
            'description' => 'meta_description_override',
            'canonical' => 'canonical_override',
            'robots' => 'robots_override',
        ] as $key => $attribute) {
            if (filled($this->getAttribute($attribute))) {
                $overrides[$key] = trim((string) $this->getAttribute($attribute));
            }
        }

        foreach ([
            'title' => 'og_title_override',
            'description' => 'og_description_override',
            'image' => 'og_image_override',
        ] as $key => $attribute) {
            if (filled($this->getAttribute($attribute))) {
                $openGraph[$key] = trim((string) $this->getAttribute($attribute));
            }
        }

        if ($openGraph !== []) {
            $overrides['open_graph'] = $openGraph;
        }

        $schema = $this->getAttribute('schema_override');

        if (is_array($schema) && $schema !== []) {
            $overrides['json_ld'] = array_is_list($schema) ? $schema : [$schema];
        }

        return $overrides;
    }

    public function effectiveHeroAlt(): string
    {
        $heroMedia = $this->getRelationValue('heroMedia');
        $storedAlt = $heroMedia instanceof Media ? $heroMedia->alt : null;

        return trim((string) (
            $this->hero_alt_override
            ?: $storedAlt
            ?: $this->title
        ));
    }

    public function routePath(): string
    {
        return "/services/{$this->slug}";
    }
}
