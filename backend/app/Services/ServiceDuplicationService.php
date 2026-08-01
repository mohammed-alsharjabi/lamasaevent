<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Service;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ServiceDuplicationService
{
    public function duplicate(Service $source, ?int $actorId): Service
    {
        $duplicate = DB::transaction(function () use ($source, $actorId): Service {
            $seoOverrides = is_array($source->seo_overrides)
                ? Arr::except($source->seo_overrides, [
                    'title', 'canonical', 'open_graph', 'twitter', 'hreflang', 'json_ld',
                ])
                : [];

            $duplicate = Service::create([
                'service_category_id' => $source->service_category_id,
                'parent_id' => $source->parent_id,
                'hero_media_id' => $source->hero_media_id,
                'title' => 'نسخة من '.$source->title,
                'excerpt' => $source->excerpt,
                'content_blocks' => $source->content_blocks,
                'status' => ContentStatus::Draft->value,
                'published_at' => null,
                'sort_order' => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'is_featured' => false,
                'seo_overrides' => $seoOverrides,
                'cta_overrides' => $source->cta_overrides,
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
