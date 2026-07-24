<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Area;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\RouteRecord;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SitemapEntry;
use App\Models\SiteSetting;
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
            ->with('seoMeta');

        return [
            'schema_version' => 1,
            'production_origin' => config('app.production_url'),
            'generated_at' => now()->toIso8601String(),
            'pages' => $published(Page::query())->get(),
            'service_categories' => $published(ServiceCategory::query())
                ->with(['services' => fn ($query) => $query->where('status', 'published')])
                ->orderBy('sort_order')
                ->get(),
            'services' => $published(Service::query())
                ->with(['category', 'media'])
                ->orderBy('sort_order')
                ->get(),
            'areas' => $published(Area::query())
                ->with('media')
                ->orderBy('sort_order')
                ->get(),
            'articles' => $published(Article::query())
                ->with('media')
                ->latest('published_at')
                ->get(),
            'galleries' => Gallery::query()
                ->where('status', 'published')
                ->with('media')
                ->get(),
            'routes' => RouteRecord::query()->where('is_published', true)->get(),
            'redirects' => Redirect::query()->where('is_active', true)->get(),
            'sitemap' => SitemapEntry::query()
                ->where('is_included', true)
                ->orderBy('position')
                ->get()
                ->map(fn (SitemapEntry $entry) => [
                    ...$entry->toArray(),
                    // A sitemap lastmod is a calendar date, not an instant. Formatting
                    // it before JSON serialization prevents a Riyadh midnight from
                    // being shifted to the previous UTC day.
                    'lastmod' => $entry->lastmod?->toDateString(),
                ]),
            'contact' => ContactSetting::first(),
            'settings' => SiteSetting::query()
                ->where('is_public', true)
                ->pluck('value', 'key'),
        ];
    }
}
