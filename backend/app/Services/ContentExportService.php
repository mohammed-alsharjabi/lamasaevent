<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\Area;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\RouteRecord;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SitemapEntry;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ContentExportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $published = fn (Builder $query) => $query
            ->where('status', ContentStatus::Published->value)
            ->with(['seoMeta', 'faqs', 'heroMedia']);

        $pages = $published(Page::query())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $serviceCategories = $published(ServiceCategory::query())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $services = $published(Service::query())
            ->with([
                'category:id,title,slug',
                'media',
                'parent:id,title,slug',
                'children' => fn ($query) => $query
                    ->where('status', ContentStatus::Published->value)
                    ->select(['id', 'parent_id', 'title', 'slug', 'sort_order']),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $areas = $published(Area::query())
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $articles = $published(Article::query())
            ->with('media')
            ->orderBy('sort_order')
            ->latest('published_at')
            ->latest('id')
            ->get();

        foreach ([$pages, $serviceCategories, $services, $areas, $articles] as $records) {
            foreach ($records as $record) {
                if (! $record instanceof ManagedContent) {
                    continue;
                }

                $seo = $record->getRelation('seoMeta');
                if ($seo instanceof SeoMeta) {
                    $keywords = $seo->getAttribute('keywords');

                    if (is_string($keywords)) {
                        $keywords = json_decode($keywords, true);
                    }

                    $seo->setAttribute(
                        'keywords',
                        app(SeoDefaultsService::class)->normalizeKeywords($keywords),
                    );
                }

                $hero = $record->getRelation('heroMedia');
                $record->unsetRelation('heroMedia');

                if (filled($record->getAttribute('legacy_path'))) {
                    continue;
                }

                $record->setAttribute('public_path', $record->routePath());
                $record->setAttribute(
                    'hero_media_summary',
                    $hero instanceof Media
                        ? [
                            'original_name' => $hero->original_name,
                            'public_url' => $hero->url(),
                            'alt' => $hero->alt,
                        ]
                        : null,
                );
            }
        }

        return [
            'schema_version' => 2,
            'production_origin' => config('app.production_url'),
            'generated_at' => now()->toIso8601String(),
            'pages' => $pages,
            'service_categories' => $serviceCategories,
            'services' => $services,
            'areas' => $areas,
            'articles' => $articles,
            'galleries' => Gallery::query()
                ->where('status', 'published')
                ->with(['media', 'items.media'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'menus' => Menu::query()
                ->where('is_active', true)
                ->with(['allItems' => fn ($query) => $query->where('is_active', true)])
                ->get(),
            'routes' => RouteRecord::query()->where('is_published', true)->get(),
            'redirects' => Redirect::query()->where('is_active', true)->get(),
            'sitemap' => SitemapEntry::query()
                ->where('is_included', true)
                ->orderBy('position')
                ->get()
                ->map(function (SitemapEntry $entry): array {
                    $lastmod = $entry->getAttribute('lastmod');

                    return [
                        ...$entry->toArray(),
                        // A sitemap lastmod is a calendar date, not an instant. Formatting
                        // it before JSON serialization prevents a Riyadh midnight from
                        // being shifted to the previous UTC day.
                        'lastmod' => $lastmod instanceof CarbonInterface
                            ? $lastmod->toDateString()
                            : $lastmod,
                    ];
                }),
            'contact' => ContactSetting::first(),
            'settings' => SiteSetting::query()
                ->where('is_public', true)
                ->where('is_sensitive', false)
                ->pluck('value', 'key'),
        ];
    }
}
