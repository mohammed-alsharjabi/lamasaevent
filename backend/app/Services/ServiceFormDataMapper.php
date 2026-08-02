<?php

namespace App\Services;

use App\Models\Service;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

class ServiceFormDataMapper
{
    /**
     * Present the first simple text and gallery as friendly top-level fields.
     * Every other block remains available in the advanced visual editor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function forForm(array $data, Service $service): array
    {
        $blocks = app(ServiceContentNormalizer::class)
            ->normalize($service->getAttribute('content_blocks'));
        $details = null;
        $gallery = [];
        $remaining = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'text' && $details === null && ! isset($block['html'])) {
                $details = $block['body'] ?? null;

                continue;
            }

            if ($type === 'gallery' && $gallery === [] && ! isset($block['html'])) {
                $gallery = (array) ($block['media_ids'] ?? []);

                continue;
            }

            $remaining[] = $block;
        }

        $data['quick_details'] = $details;
        $data['quick_gallery_media_ids'] = $gallery;
        $data['content_blocks'] = $remaining;

        return $data;
    }

    /**
     * Convert the simple fields back into the canonical JSON block list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function forStorage(array $data): array
    {
        $details = $this->richText($data['quick_details'] ?? null);
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

        if (trim(strip_tags(html_entity_decode($details))) !== '') {
            $quickBlocks[] = [
                'type' => 'text',
                'heading' => null,
                'body' => $details,
            ];
        }

        if ($galleryIds !== []) {
            $quickBlocks[] = [
                'type' => 'gallery',
                'heading' => 'معرض الصور',
                'media_ids' => $galleryIds,
            ];
        }

        $data['content_blocks'] = [
            ...$intro,
            ...$quickBlocks,
            ...$blocks,
        ];

        unset(
            $data['quick_details'],
            $data['quick_gallery_media_ids'],
            $data['service_template'],
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
