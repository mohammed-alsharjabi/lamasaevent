<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class PreviewArticleController extends Controller
{
    public function __invoke(Article $article): View
    {
        Gate::authorize('view', $article);
        $article->load(['heroMedia', 'category', 'faqs']);
        $blocks = $article->getAttribute('content_blocks');
        $mediaIds = [];

        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'gallery') {
                    continue;
                }

                foreach ((array) ($block['media_ids'] ?? []) as $mediaId) {
                    $mediaIds[(int) $mediaId] = (int) $mediaId;
                }
            }
        }

        return view('admin.articles.preview', [
            'article' => $article,
            'galleryMedia' => Media::query()
                ->whereKey(array_values($mediaIds))
                ->get()
                ->keyBy('id'),
        ]);
    }
}
