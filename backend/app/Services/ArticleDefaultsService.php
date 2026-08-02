<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Article;
use Illuminate\Support\Str;

class ArticleDefaultsService
{
    public function apply(Article $article): void
    {
        if (! $article->uses_generated_defaults) {
            return;
        }

        $this->applySlugOverride($article);

        if (! $article->exists && $article->getAttribute('sort_order') === null) {
            $article->sort_order = $this->nextSortOrder($article);
        }

        if ($this->status($article) === ContentStatus::Published && ! $article->published_at) {
            $article->published_at = now();
        }

        $article->setAttribute(
            'content_blocks',
            app(ServiceContentNormalizer::class)
                ->normalize($article->getAttribute('content_blocks')),
        );
    }

    public function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($value) ?: 'article', 160, '');
        $candidate = $base;
        $suffix = 2;

        while (Article::query()
            ->withTrashed()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = Str::limit($base, 150, '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function nextSortOrder(Article $article): int
    {
        return ((int) Article::query()
            ->where('article_category_id', $article->article_category_id)
            ->max('sort_order')) + 1;
    }

    private function applySlugOverride(Article $article): void
    {
        if (filled($article->legacy_path) || blank($article->slug_override)) {
            return;
        }

        $requested = Str::slug((string) $article->slug_override);

        if ($requested === '') {
            $article->slug_override = null;

            return;
        }

        $article->slug_override = $requested;

        // Published routes are changed after save by SlugRedirectService so
        // the old URL always receives a permanent redirect.
        $originalStatus = (string) $article->getRawOriginal('status');

        if ($article->exists && $originalStatus === ContentStatus::Published->value) {
            return;
        }

        $article->slug = $this->uniqueSlug(
            $requested,
            $article->exists ? (int) $article->getKey() : null,
        );
        $article->slug_override = $article->slug;
    }

    private function status(Article $article): ContentStatus
    {
        $status = $article->status;

        return $status instanceof ContentStatus
            ? $status
            : ContentStatus::from((string) ($status ?: ContentStatus::Draft->value));
    }
}
