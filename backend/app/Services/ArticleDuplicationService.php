<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Faq;
use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ArticleDuplicationService
{
    public function duplicate(Article $source, ?int $actorId): Article
    {
        $duplicate = DB::transaction(function () use ($source, $actorId): Article {
            $seoOverrides = is_array($source->seo_overrides)
                ? Arr::except($source->seo_overrides, [
                    'title', 'canonical', 'open_graph', 'twitter', 'hreflang', 'json_ld',
                ])
                : [];

            $duplicate = Article::create([
                'article_category_id' => $source->article_category_id,
                'hero_media_id' => $source->hero_media_id,
                'title' => 'نسخة من '.$source->title,
                'topic' => $source->topic,
                'excerpt' => $source->excerpt,
                'content_blocks' => $source->content_blocks,
                'status' => ContentStatus::Draft->value,
                'published_at' => null,
                'sort_order' => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'is_featured' => false,
                'internal_keywords' => $source->internal_keywords,
                'seo_overrides' => $seoOverrides,
                'meta_description_override' => $source->meta_description_override,
                'robots_override' => $source->robots_override,
                'og_description_override' => $source->og_description_override,
                'og_image_override' => $source->og_image_override,
                'target_search_phrase' => $source->target_search_phrase,
                'hero_alt_override' => $source->hero_alt_override,
                'uses_generated_defaults' => true,
            ]);

            foreach ($source->faqs()->orderBy('sort_order')->get() as $faq) {
                if (! $faq instanceof Faq) {
                    continue;
                }

                $duplicate->faqs()->create([
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'sort_order' => $faq->sort_order,
                    'is_active' => $faq->is_active,
                ]);
            }

            $media = $source->media()
                ->get()
                ->filter(fn ($item): bool => $item instanceof Media)
                ->mapWithKeys(function (Media $item): array {
                    $pivot = $item->getRelation('pivot');

                    return [
                        $item->getKey() => [
                            'role' => $pivot instanceof Pivot ? $pivot->getAttribute('role') : 'gallery',
                            'sort_order' => $pivot instanceof Pivot ? $pivot->getAttribute('sort_order') : 0,
                        ],
                    ];
                })
                ->all();

            if ($media !== []) {
                $duplicate->media()->sync($media);
            }

            return $duplicate;
        }, 3);

        app(ContentPublishingService::class)->sync($duplicate, $actorId);

        return $duplicate;
    }
}
