<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
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
use Illuminate\Http\JsonResponse;

class ContentExportController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $published = fn (Builder $query) => $query
            ->where('status', ContentStatus::Published->value)
            ->with('seoMeta');

        $payload = [
            'schema_version' => 1,
            'production_origin' => config('app.production_url'),
            'pages' => $published(Page::query())->get(),
            'service_categories' => $published(ServiceCategory::query())
                ->with(['services' => fn ($query) => $query->where('status', 'published')])
                ->orderBy('sort_order')
                ->get(),
            'services' => $published(Service::query())
                ->with('category')
                ->orderBy('sort_order')
                ->get(),
            'areas' => $published(Area::query())->orderBy('sort_order')->get(),
            'articles' => $published(Article::query())
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
                ->get(),
            'contact' => ContactSetting::first(),
            'settings' => SiteSetting::query()
                ->where('is_public', true)
                ->pluck('value', 'key'),
        ];

        $etag = '"'.hash('sha256', json_encode($payload)).'"';

        return response()
            ->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
