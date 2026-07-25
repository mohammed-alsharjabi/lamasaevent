<?php

namespace App\Console\Commands;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\Area;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ContactSetting;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Menu;
use App\Models\Page;
use App\Models\RouteRecord;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SitemapEntry;
use App\Models\SiteSetting;
use App\Services\ImageProcessor;
use App\Services\LegacyHtmlParser;
use App\Services\LegacyManifestService;
use App\Services\LegacyVerificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
        LegacyManifestService $manifests,
        LegacyVerificationService $verification,
    ): int {
        $inspection = $manifests->load(
            $this->option('legacy'),
            $this->option('manifest'),
        );
        $legacyRoot = $inspection['legacy_root'];
        $routes = $inspection['routes'];

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
                $this->syncFaqs($entity, $parsed['faqs']);
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
        $this->syncMenus($parsedRoutes['/']['menus'] ?? []);
        Cache::forget('content-export:v2');
        $report = $verification->verify($routes);
        if (! $report['passed']) {
            throw new RuntimeException('Post-import verification failed. Run legacy:verify for details.');
        }
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
    ): Article|Service|ServiceCategory|Area|Page {
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
                    'article_category_id' => $this->articleCategoryId($parsed['topic']),
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

    private function articleCategoryId(?string $topic): ?int
    {
        if (blank($topic)) {
            return null;
        }

        $slug = Str::slug($topic);
        $slug = $slug !== '' ? $slug : 'topic-'.substr(sha1($topic), 0, 12);

        return ArticleCategory::updateOrCreate(
            ['slug' => $slug],
            ['name' => $topic, 'is_active' => true],
        )->getKey();
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function syncSeo(Model&ManagedContent $entity, array $seo): void
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
     * @param  array<int, array{question: string, answer: string}>  $faqs
     */
    private function syncFaqs(Model&ManagedContent $entity, array $faqs): void
    {
        $ids = [];
        foreach ($faqs as $position => $faq) {
            $record = $entity->faqs()->updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => $position,
                    'is_active' => true,
                ],
            );
            $ids[] = $record->getKey();
        }

        $entity->faqs()->when(
            $ids !== [],
            fn ($query) => $query->whereNotIn('id', $ids),
        )->delete();
    }

    /**
     * @param  array<string, mixed>  $route
     */
    private function syncRoute(Model&ManagedContent $entity, array $route): RouteRecord
    {
        $record = $entity->routeRecord()->updateOrCreate([], [
            'path' => $route['path'],
            'exact_url' => $route['url'],
            'is_legacy' => true,
            'slug_locked' => true,
            'is_published' => true,
            'legacy_html_sha256' => $route['legacy_html_sha256'],
            'published_at' => $entity->getAttribute('published_at'),
        ]);

        if (! $record instanceof RouteRecord) {
            throw new RuntimeException('Unexpected route record model.');
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $route
     */
    private function syncSitemap(RouteRecord $routeRecord, array $route): void
    {
        SitemapEntry::updateOrCreate(['loc' => $route['url']], [
            'route_registry_id' => $routeRecord->getKey(),
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
        Model&ManagedContent $entity,
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
            if (blank($media->getAttribute('alt')) && filled($image['alt'])) {
                $media->update(['alt' => $image['alt']]);
            }
            $firstMediaId ??= $media->getKey();
            $pivot[$media->getKey()] = [
                'role' => $image['role'],
                'sort_order' => $index,
            ];
        }

        $entity->media()->sync($pivot);
        if ($firstMediaId && $entity->isFillable('hero_media_id')) {
            $entity->forceFill(['hero_media_id' => $firstMediaId])->saveQuietly();
        }

        if ($route['type'] === 'gallery') {
            $gallery = Gallery::updateOrCreate(
                ['slug' => 'main'],
                [
                    'title' => $entity->getAttribute('title'),
                    'description' => $entity->getAttribute('summary'),
                    'status' => ContentStatus::Published,
                    'published_at' => $entity->getAttribute('published_at'),
                ],
            );
            $galleryPivot = collect($pivot)
                ->map(fn ($values) => ['sort_order' => $values['sort_order']])
                ->all();
            $gallery->media()->sync($galleryPivot);
            $galleryItemIds = [];

            foreach ($pivot as $mediaId => $values) {
                $item = GalleryItem::updateOrCreate(
                    ['gallery_id' => $gallery->getKey(), 'media_id' => $mediaId],
                    [
                        'sort_order' => $values['sort_order'],
                        'is_active' => true,
                    ],
                );
                $galleryItemIds[] = $item->getKey();
            }

            GalleryItem::where('gallery_id', $gallery->getKey())
                ->when(
                    $galleryItemIds !== [],
                    fn ($query) => $query->whereNotIn('id', $galleryItemIds),
                )
                ->delete();
        }
    }

    /**
     * @param  array<string, array<int, array{label: string, url: string}>>  $menus
     */
    private function syncMenus(array $menus): void
    {
        foreach (['header' => 'القائمة الرئيسية', 'footer' => 'قائمة التذييل'] as $location => $name) {
            $menu = Menu::updateOrCreate(
                ['location' => $location],
                ['name' => $name, 'is_active' => true],
            );
            $ids = [];

            foreach ($menus[$location] ?? [] as $position => $item) {
                $record = $menu->allItems()->updateOrCreate(
                    ['url' => $item['url'], 'label' => $item['label']],
                    [
                        'sort_order' => $position,
                        'is_external' => str_starts_with($item['url'], 'http'),
                        'open_in_new_tab' => false,
                        'is_active' => true,
                    ],
                );
                $ids[] = $record->getKey();
            }

            $menu->allItems()
                ->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))
                ->delete();
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
