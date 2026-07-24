<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\Area;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\RouteRecord;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SitemapEntry;
use App\Models\SiteSetting;
use App\Services\ImageProcessor;
use App\Services\LegacyHtmlParser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportLegacySite extends Command
{
    protected $signature = 'legacy:import
        {--legacy= : Absolute path to the immutable legacy-dist}
        {--manifest= : Path to route-manifest.json}
        {--skip-media : Import content without copying and converting media}
        {--dry-run : Verify and parse everything without writing to the database}';

    protected $description = 'Import the immutable Astro production build into structured CMS data';

    public function handle(
        LegacyHtmlParser $parser,
        ImageProcessor $images,
    ): int {
        $legacyRoot = realpath($this->option('legacy') ?: env('LEGACY_DIST', ''));
        $manifestPath = realpath(
            $this->option('manifest') ?: env('LEGACY_MANIFEST', base_path('../route-manifest.json')),
        );

        if (! $legacyRoot || ! is_dir($legacyRoot)) {
            $this->error('Pass a readable legacy-dist directory with --legacy.');

            return self::FAILURE;
        }
        if (! $manifestPath || ! is_file($manifestPath)) {
            $this->error('route-manifest.json was not found.');

            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $sitemapPath = $legacyRoot.DIRECTORY_SEPARATOR.'sitemap.xml';
        $actualSitemapHash = hash_file('sha256', $sitemapPath);

        if (! hash_equals($manifest['legacy']['sitemap_sha256'], $actualSitemapHash)) {
            throw new RuntimeException('legacy-dist/sitemap.xml changed after the recovery audit.');
        }

        $routes = $manifest['routes'];
        if (count($routes) !== $manifest['legacy']['route_count']) {
            throw new RuntimeException('Route manifest count is inconsistent.');
        }

        $parsedRoutes = [];
        $categoryByServiceSlug = [];
        foreach ($routes as $route) {
            $htmlPath = $this->safeLegacyPath($legacyRoot, $route['legacy_file']);
            $html = file_get_contents($htmlPath);
            if (! hash_equals($route['legacy_html_sha256'], hash('sha256', $html))) {
                throw new RuntimeException("Legacy HTML changed: {$route['legacy_file']}");
            }

            $parsed = $parser->parse($html, $route['legacy_file']);
            $parsedRoutes[$route['path']] = $parsed;
            if ($route['type'] === 'service_category') {
                $categorySlug = $this->slugFromPath($route['path']);
                foreach ($parsed['service_links'] as $serviceSlug) {
                    $categoryByServiceSlug[$serviceSlug] ??= $categorySlug;
                }
            }
        }

        $this->info(sprintf(
            'Verified %d routes against immutable SHA-256 checksums.',
            count($routes),
        ));

        if ($this->option('dry-run')) {
            $this->info('Dry run complete; no database or media files were changed.');

            return self::SUCCESS;
        }

        $progress = $this->output->createProgressBar(count($routes));
        foreach ($routes as $route) {
            DB::transaction(function () use (
                $route,
                $parsedRoutes,
                $categoryByServiceSlug,
                $legacyRoot,
                $images,
            ): void {
                $parsed = $parsedRoutes[$route['path']];
                $entity = $this->upsertEntity($route, $parsed, $categoryByServiceSlug);
                $this->syncSeo($entity, $route['seo']);
                $routeRecord = $this->syncRoute($entity, $route);
                $this->syncSitemap($routeRecord, $route);

                if (! $this->option('skip-media')) {
                    $this->syncMedia($entity, $parsed['images'], $legacyRoot, $images, $route);
                }
            });
            $progress->advance();
        }
        $progress->finish();
        $this->newLine(2);

        $this->seedGlobalSettings();
        $this->info('Legacy import completed successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $parsed
     * @param  array<string, string>  $categoryByServiceSlug
     */
    private function upsertEntity(
        array $route,
        array $parsed,
        array $categoryByServiceSlug,
    ): Model {
        $slug = $this->slugFromPath($route['path']);
        $publishedAt = $parsed['published_at']
            ? Carbon::parse($parsed['published_at'])
            : Carbon::parse($route['sitemap']['lastmod'])->startOfDay();
        $common = [
            'title' => $parsed['title'],
            'content_blocks' => $parsed['content_blocks'],
            'status' => ContentStatus::Published,
            'published_at' => $publishedAt,
            'legacy_path' => $route['path'],
        ];

        return match ($route['type']) {
            'article' => Article::updateOrCreate(
                ['legacy_path' => $route['path']],
                [
                    ...$common,
                    'slug' => $slug,
                    'topic' => $parsed['topic'],
                    'excerpt' => $parsed['summary'],
                ],
            ),
            'service' => Service::updateOrCreate(
                ['legacy_path' => $route['path']],
                [
                    ...$common,
                    'slug' => $slug,
                    'excerpt' => $parsed['summary'],
                    'service_category_id' => isset($categoryByServiceSlug[$slug])
                        ? ServiceCategory::where('slug', $categoryByServiceSlug[$slug])->value('id')
                        : null,
                    'sort_order' => $route['order'],
                ],
            ),
            'service_category' => ServiceCategory::updateOrCreate(
                ['legacy_path' => $route['path']],
                [
                    ...$common,
                    'slug' => $slug,
                    'summary' => $parsed['summary'],
                    'sort_order' => $route['order'],
                ],
            ),
            'area' => Area::updateOrCreate(
                ['legacy_path' => $route['path']],
                [
                    ...$common,
                    'slug' => $slug,
                    'summary' => $parsed['summary'],
                    'sort_order' => $route['order'],
                ],
            ),
            default => Page::updateOrCreate(
                ['legacy_path' => $route['path']],
                [
                    ...$common,
                    'type' => $route['type'],
                    'path' => $route['path'],
                    'summary' => $parsed['summary'],
                ],
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function syncSeo(Model $entity, array $seo): void
    {
        $entity->seoMeta()->updateOrCreate([], [
            'title' => $seo['title'],
            'description' => $seo['meta_description'],
            'canonical' => $seo['canonical'],
            'robots' => $seo['robots'],
            'open_graph' => $seo['open_graph'],
            'twitter' => $seo['twitter'],
            'hreflang' => [
                ['lang' => 'ar-SA', 'href' => $seo['canonical']],
                ['lang' => 'x-default', 'href' => $seo['canonical']],
            ],
            'json_ld' => $seo['json_ld'],
            'json_ld_sha256' => $seo['json_ld_sha256'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $route
     */
    private function syncRoute(Model $entity, array $route): RouteRecord
    {
        return $entity->routeRecord()->updateOrCreate([], [
            'path' => $route['path'],
            'exact_url' => $route['url'],
            'is_legacy' => true,
            'slug_locked' => true,
            'is_published' => true,
            'legacy_html_sha256' => $route['legacy_html_sha256'],
            'published_at' => $entity->published_at,
        ]);
    }

    /**
     * @param  array<string, mixed>  $route
     */
    private function syncSitemap(RouteRecord $routeRecord, array $route): void
    {
        SitemapEntry::updateOrCreate(['loc' => $route['url']], [
            'route_registry_id' => $routeRecord->id,
            'path' => $route['path'],
            'lastmod' => $route['sitemap']['lastmod'],
            'changefreq' => $route['sitemap']['changefreq'],
            'priority' => $route['sitemap']['priority'],
            'position' => $route['order'],
            'is_included' => true,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedImages
     * @param  array<string, mixed>  $route
     */
    private function syncMedia(
        Model $entity,
        array $parsedImages,
        string $legacyRoot,
        ImageProcessor $processor,
        array $route,
    ): void {
        $pivot = [];
        $firstMediaId = null;
        foreach ($parsedImages as $index => $image) {
            $relative = ltrim(rawurldecode(parse_url($image['src'], PHP_URL_PATH)), '/');
            $absolute = $this->safeLegacyPath($legacyRoot, $relative);
            $media = $processor->import($absolute, $relative);
            if (blank($media->alt) && filled($image['alt'])) {
                $media->update(['alt' => $image['alt']]);
            }
            $firstMediaId ??= $media->id;
            $pivot[$media->id] = [
                'role' => $image['role'],
                'sort_order' => $index,
            ];
        }

        if (method_exists($entity, 'media')) {
            $entity->media()->sync($pivot);
        }
        if ($firstMediaId && $entity->isFillable('hero_media_id')) {
            $entity->forceFill(['hero_media_id' => $firstMediaId])->saveQuietly();
        }

        if ($route['type'] === 'gallery') {
            $gallery = Gallery::updateOrCreate(
                ['slug' => 'main'],
                [
                    'title' => $entity->title,
                    'description' => $entity->summary,
                    'status' => ContentStatus::Published,
                    'published_at' => $entity->published_at,
                ],
            );
            $galleryPivot = collect($pivot)
                ->map(fn ($values) => ['sort_order' => $values['sort_order']])
                ->all();
            $gallery->media()->sync($galleryPivot);
        }
    }

    private function seedGlobalSettings(): void
    {
        ContactSetting::updateOrCreate(['id' => 1], [
            'phone' => '+966502560106',
            'phone_display' => '050 256 0106',
            'whatsapp' => '966502560106',
            'email' => 'info@lams-event.com',
            'city' => 'الرياض',
            'region' => 'منطقة الرياض',
            'country_code' => 'SA',
        ]);

        foreach ([
            'site_name' => ['text' => 'لمسه التميز للحفلات'],
            'site_description' => ['text' => 'تنسيق وتجهيز مناسبات في الرياض'],
            'default_robots' => ['text' => 'index, follow, max-image-preview:large'],
        ] as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'group' => 'seo',
                'is_public' => true,
            ]);
        }
    }

    private function safeLegacyPath(string $legacyRoot, string $relative): string
    {
        $candidate = realpath($legacyRoot.DIRECTORY_SEPARATOR.ltrim($relative, '/'));
        $prefix = rtrim($legacyRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! $candidate || ! str_starts_with($candidate, $prefix) || ! is_file($candidate)) {
            throw new RuntimeException("Unsafe or missing legacy path: {$relative}");
        }

        return $candidate;
    }

    private function slugFromPath(string $path): string
    {
        return Str::of($path)->trim('/')->afterLast('/')->toString() ?: 'home';
    }
}
