<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\ActivityLog;
use App\Models\RouteRecord;
use App\Models\SitemapEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentPublishingService
{
    public function sync(Model $content, ?int $actorId = null): void
    {
        if (! $content instanceof ManagedContent) {
            return;
        }

        DB::transaction(function () use ($content, $actorId): void {
            $status = $content->getAttribute('status');

            if (! $status instanceof ContentStatus) {
                return;
            }

            if (
                $status === ContentStatus::Published
                && ! $content->getAttribute('published_at')
            ) {
                $content->forceFill(['published_at' => now()])->saveQuietly();
            }

            $published = $status === ContentStatus::Published;
            $path = $content->routePath();
            $route = $content->routeRecord()->updateOrCreate([], [
                'path' => $path,
                'exact_url' => rtrim(config('app.production_url'), '/').$path,
                'is_legacy' => filled($content->getAttribute('legacy_path')),
                'slug_locked' => $published,
                'is_published' => $published,
                'published_at' => $content->getAttribute('published_at'),
            ]);

            if (! $route instanceof RouteRecord) {
                return;
            }

            app(SeoDefaultsService::class)->sync($content, $path);
            $this->syncSitemap($route, $published);

            ActivityLog::create([
                'user_id' => $actorId,
                'action' => $published ? 'content.published' : 'content.saved_as_draft',
                'subject_type' => $content->getMorphClass(),
                'subject_id' => $content->getKey(),
                'after' => [
                    'path' => $route->getAttribute('path'),
                    'status' => $status->value,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
            ]);
        }, 3);

        Cache::forget(ContentExportService::CACHE_KEY);
        Cache::forget(PublicPageRenderer::CACHE_KEY);
        Cache::forget('sitemap-xml:v1');
        app(PublishPipeline::class)->queue($content, $actorId);
    }

    private function syncSitemap(RouteRecord $route, bool $published): void
    {
        if ($route->is_legacy) {
            return;
        }

        $entry = SitemapEntry::query()->firstOrNew(['path' => $route->path]);

        if (! $entry->exists) {
            $entry->position = ((int) SitemapEntry::query()->max('position')) + 1;
            $entry->changefreq = 'monthly';
            $entry->priority = $this->defaultPriority($route->path);
        }

        $entry->fill([
            'route_registry_id' => $route->getKey(),
            'loc' => rtrim(config('app.production_url'), '/').$route->path,
            'lastmod' => now()->toDateString(),
            'is_included' => $published,
        ])->save();
    }

    private function defaultPriority(string $path): float
    {
        return match (true) {
            $path === '/' => 1.0,
            str_starts_with($path, '/services/') => 0.8,
            str_starts_with($path, '/blog/') => 0.7,
            str_starts_with($path, '/areas/') => 0.7,
            default => 0.6,
        };
    }
}
