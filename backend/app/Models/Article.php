<?php

namespace App\Models;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\Concerns\GeneratesInternalSlug;
use App\Models\Concerns\HasManagedContent;
use App\Services\ArticleDefaultsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model implements ManagedContent
{
    use GeneratesInternalSlug, HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'hero_media_id', 'article_category_id', 'title', 'slug', 'topic', 'excerpt',
        'content_blocks', 'status', 'published_at', 'legacy_path', 'created_by',
        'updated_by', 'is_featured', 'internal_keywords', 'sort_order',
        'seo_overrides', 'uses_generated_defaults', 'seo_title_override',
        'meta_description_override', 'slug_override', 'canonical_override',
        'robots_override', 'og_title_override', 'og_description_override',
        'og_image_override', 'schema_override', 'target_search_phrase',
        'hero_alt_override',
    ];

    protected $hidden = [
        'seo_overrides', 'uses_generated_defaults', 'seo_title_override',
        'meta_description_override', 'slug_override', 'canonical_override',
        'robots_override', 'og_title_override', 'og_description_override',
        'og_image_override', 'schema_override', 'target_search_phrase',
        'hero_alt_override',
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
            'seo_overrides' => 'array',
            'schema_override' => 'array',
            'uses_generated_defaults' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Article $article): void {
            if (! array_key_exists('uses_generated_defaults', $article->getAttributes())) {
                $article->uses_generated_defaults = blank($article->legacy_path);
            }
        });

        static::saving(fn (Article $article) => app(ArticleDefaultsService::class)
            ->apply($article));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
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
        return "/blog/{$this->slug}";
    }
}
