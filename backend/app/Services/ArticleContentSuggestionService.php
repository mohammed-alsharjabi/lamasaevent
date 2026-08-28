<?php

namespace App\Services;

use App\Models\ArticleCategory;

class ArticleContentSuggestionService
{
    /**
     * @param  array<string, mixed>  $state
     * @return array{suggested_excerpt: string, suggested_body: string}
     */
    public function suggest(array $state): array
    {
        $title = trim((string) ($state['title'] ?? 'المقال')) ?: 'المقال';
        $category = ArticleCategory::query()
            ->find($state['article_category_id'] ?? null)?->name;
        $context = filled($category) ? ' ضمن '.$category : '';
        $excerpt = trim(strip_tags((string) ($state['excerpt'] ?? '')));
        $excerpt = $excerpt !== ''
            ? $excerpt
            : "دليل مختصر عن {$title}{$context} يوضح أهم النقاط التي يحتاجها القارئ بصورة مباشرة وعملية.";

        return [
            'suggested_excerpt' => $excerpt,
            'suggested_body' => '<p>'.e($excerpt).'</p>',
        ];
    }
}
