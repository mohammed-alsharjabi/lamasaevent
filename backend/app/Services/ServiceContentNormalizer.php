<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ServiceContentNormalizer
{
    private const MANAGED_TYPES = [
        'intro', 'text', 'features', 'steps', 'gallery', 'cta', 'faq',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function normalize(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        return collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(function (array $block): array {
                $type = (string) ($block['type'] ?? '');

                // Immutable restored blocks keep their original structure and HTML.
                if (! in_array($type, self::MANAGED_TYPES, true)) {
                    return $block;
                }

                return match ($type) {
                    'intro' => $this->intro($block),
                    'text' => $this->text($block),
                    'features', 'steps' => $this->items($type, $block),
                    'gallery' => $this->gallery($block),
                    'cta' => $this->cta($block),
                    'faq' => $this->faq($block),
                };
            })
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $block */
    private function intro(array $block): array
    {
        return [
            'type' => 'intro',
            'heading' => $this->plain($block['heading'] ?? null, 255),
            'lead' => $this->rich($block['lead'] ?? null),
        ];
    }

    /** @param array<string, mixed> $block */
    private function text(array $block): array
    {
        return [
            'type' => 'text',
            'heading' => $this->plain($block['heading'] ?? null, 255),
            'body' => $this->rich($block['body'] ?? null),
        ];
    }

    /** @param array<string, mixed> $block */
    private function items(string $type, array $block): array
    {
        return [
            'type' => $type,
            'heading' => $this->plain($block['heading'] ?? null, 255),
            'items' => collect(Arr::wrap($block['items'] ?? []))
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => [
                    'title' => $this->plain($item['title'] ?? null, 255),
                    'description' => $this->plain($item['description'] ?? null, 1000),
                ])
                ->filter(fn (array $item): bool => filled($item['title']) || filled($item['description']))
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $block */
    private function gallery(array $block): array
    {
        return [
            'type' => 'gallery',
            'heading' => $this->plain($block['heading'] ?? null, 255),
            'media_ids' => collect(Arr::wrap($block['media_ids'] ?? []))
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $block */
    private function cta(array $block): array
    {
        $url = trim((string) ($block['url'] ?? ''));

        if ($url !== '' && ! str_starts_with($url, '/') && ! filter_var($url, FILTER_VALIDATE_URL)) {
            $url = '';
        }

        return [
            'type' => 'cta',
            'heading' => $this->plain($block['heading'] ?? null, 255),
            'text' => $this->plain($block['text'] ?? null, 1000),
            'label' => $this->plain($block['label'] ?? null, 120),
            'url' => $url !== '' ? $url : null,
        ];
    }

    /** @param array<string, mixed> $block */
    private function faq(array $block): array
    {
        return [
            'type' => 'faq',
            'heading' => $this->plain($block['heading'] ?? null, 255),
            'items' => collect(Arr::wrap($block['items'] ?? []))
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => [
                    'question' => $this->plain($item['question'] ?? null, 500),
                    'answer' => $this->plain($item['answer'] ?? null, 2000),
                ])
                ->filter(fn (array $item): bool => filled($item['question']) && filled($item['answer']))
                ->values()
                ->all(),
        ];
    }

    private function plain(mixed $value, int $limit): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    private function rich(mixed $value): ?string
    {
        $html = trim((string) $value);

        if ($html === '') {
            return null;
        }

        $html = strip_tags(
            $html,
            '<p><br><strong><b><em><i><u><a><h2><h3><blockquote><ul><ol><li>',
        );
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;
        $html = preg_replace('/(href\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/iu', '$1#$2', $html) ?? $html;

        return Str::limit($html, 50000, '');
    }
}
