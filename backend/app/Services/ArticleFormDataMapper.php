<?php

namespace App\Services;

use App\Models\Article;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

class ArticleFormDataMapper
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function forForm(array $data, Article $article): array
    {
        $blocks = app(ServiceContentNormalizer::class)
            ->normalize($article->getAttribute('content_blocks'));
        $body = null;
        $gallery = [];
        $remaining = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'text' && $body === null && ! isset($block['html'])) {
                $body = $block['body'] ?? null;

                continue;
            }

            if ($type === 'gallery' && $gallery === [] && ! isset($block['html'])) {
                $gallery = (array) ($block['media_ids'] ?? []);

                continue;
            }

            $remaining[] = $block;
        }

        $data['quick_body'] = $body;
        $data['quick_gallery_media_ids'] = $gallery;
        $data['content_blocks'] = $remaining;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function forStorage(array $data): array
    {
        $body = $this->richText($data['quick_body'] ?? null);
        $galleryIds = collect((array) ($data['quick_gallery_media_ids'] ?? []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $blocks = app(ServiceContentNormalizer::class)
            ->normalize($data['content_blocks'] ?? []);
        $intro = [];

        while (($blocks[0]['type'] ?? null) === 'intro') {
            $intro[] = array_shift($blocks);
        }

        $quickBlocks = [];

        if (trim(strip_tags(html_entity_decode($body))) !== '') {
            $quickBlocks[] = [
                'type' => 'text',
                'heading' => null,
                'body' => $body,
            ];
        }

        if ($galleryIds !== []) {
            $quickBlocks[] = [
                'type' => 'gallery',
                'heading' => 'صور المقال',
                'media_ids' => $galleryIds,
            ];
        }

        $data['content_blocks'] = [
            ...$intro,
            ...$quickBlocks,
            ...$blocks,
        ];

        unset(
            $data['quick_body'],
            $data['quick_gallery_media_ids'],
            $data['developer_mode'],
        );

        return $data;
    }

    private function richText(mixed $value): string
    {
        if (is_array($value)) {
            return trim(RichContentRenderer::make($value)->toHtml());
        }

        return trim((string) $value);
    }
}
