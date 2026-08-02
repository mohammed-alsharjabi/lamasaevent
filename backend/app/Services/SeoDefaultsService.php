<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Faq;
use App\Models\Media;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SeoDefaultsService
{
    public function sync(Model $content, string $path): SeoMeta
    {
        if (! $content instanceof ManagedContent) {
            throw new InvalidArgumentException('SEO defaults require managed content.');
        }

        $seo = $content->seoMeta()->first();

        if (! $seo instanceof SeoMeta) {
            $seo = new SeoMeta;
        }
        $usesGeneratedDefaults = $content instanceof Service
            && $content->uses_generated_defaults;
        $overrides = $usesGeneratedDefaults
            ? $content->effectiveSeoOverrides()
            : [];
        $title = $this->title($content, $usesGeneratedDefaults);
        $description = $this->description($content, $title);
        $canonical = rtrim((string) config('app.production_url'), '/').$path;

        if ($usesGeneratedDefaults) {
            $computed = $this->previewService($content, $path);
            $seo->title = $computed['title'];
            $seo->description = $computed['description'];
            $seo->canonical = $computed['canonical'];
            $seo->robots = $computed['robots'];
        } elseif (blank($seo->title)) {
            $seo->title = $title;
        }

        if (! $usesGeneratedDefaults && blank($seo->description)) {
            $seo->description = $description;
        }

        if (
            ! $usesGeneratedDefaults &&
            blank($seo->canonical)
            || (
                ! $usesGeneratedDefaults &&
                blank($content->getAttribute('legacy_path'))
                && $this->isLocalUrl((string) $seo->canonical)
            )
        ) {
            $seo->canonical = $canonical;
        }

        if (! $usesGeneratedDefaults && blank($seo->robots)) {
            $seo->robots = 'index, follow';
        }

        $seo->setAttribute(
            'keywords',
            $this->normalizeKeywords(
                $usesGeneratedDefaults
                    ? ($overrides['keywords'] ?? [])
                    : $seo->getAttribute('keywords'),
            ),
        );

        if ($usesGeneratedDefaults) {
            $seo->setAttribute('open_graph', $computed['open_graph']);
        } elseif (blank($seo->getAttribute('open_graph'))) {
            $seo->setAttribute('open_graph', [
                'type' => $content instanceof Article ? 'article' : 'website',
                'locale' => 'ar_SA',
                'site_name' => $this->siteName(),
                'title' => (string) $seo->title,
                'description' => (string) $seo->description,
                'url' => (string) $seo->canonical,
            ]);
        }

        if ($usesGeneratedDefaults) {
            $seo->setAttribute('twitter', $computed['twitter']);
        } elseif (blank($seo->getAttribute('twitter'))) {
            $seo->setAttribute('twitter', [
                'card' => 'summary',
                'title' => (string) $seo->title,
                'description' => (string) $seo->description,
            ]);
        }

        if ($usesGeneratedDefaults) {
            $seo->setAttribute('hreflang', $computed['hreflang']);
        } elseif (blank($seo->getAttribute('hreflang'))) {
            $seo->setAttribute('hreflang', [
                ['lang' => 'ar-SA', 'href' => (string) $seo->canonical],
                ['lang' => 'x-default', 'href' => (string) $seo->canonical],
            ]);
        }

        if ($usesGeneratedDefaults) {
            $seo->setAttribute('json_ld', $computed['json_ld']);
        } elseif ($seo->getAttribute('json_ld') === null) {
            $seo->setAttribute('json_ld', []);
        }

        if (blank($seo->json_ld_sha256)) {
            $seo->json_ld_sha256 = hash(
                'sha256',
                json_encode(
                    $seo->getAttribute('json_ld'),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ) ?: '[]',
            );
        }

        $content->seoMeta()->save($seo);
        $content->setRelation('seoMeta', $seo);

        return $seo;
    }

    /**
     * Calculate the exact public SEO payload without writing it. Filament uses
     * this for honest previews and checks; sync() persists the same values.
     *
     * @return array{
     *   title: string,
     *   description: string,
     *   canonical: string,
     *   robots: string,
     *   open_graph: array<mixed>,
     *   twitter: array<mixed>,
     *   hreflang: array<mixed>,
     *   json_ld: array<mixed>
     * }
     */
    public function previewService(Service $service, ?string $path = null): array
    {
        $overrides = $service->effectiveSeoOverrides();
        $title = $this->title($service, true);
        $description = $this->description($service, $title);
        $path ??= $service->routePath();
        $canonical = rtrim((string) config('app.production_url'), '/').$path;
        $title = $this->override($overrides, 'title', $title);
        $description = $this->override($overrides, 'description', $description);
        $canonical = $this->override($overrides, 'canonical', $canonical);
        $robots = $this->override(
            $overrides,
            'robots',
            $service->isPublished() ? 'index,follow' : 'noindex,nofollow',
        );
        $seo = new SeoMeta([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
        ]);
        $openGraph = array_replace(
            $this->openGraph($service, $seo),
            is_array($overrides['open_graph'] ?? null)
                ? $overrides['open_graph']
                : [],
        );
        $twitter = array_replace([
            'card' => filled($openGraph['image'] ?? null)
                ? 'summary_large_image'
                : 'summary',
            'title' => (string) ($openGraph['title'] ?? $title),
            'description' => (string) ($openGraph['description'] ?? $description),
            'image' => $openGraph['image'] ?? null,
        ], is_array($overrides['twitter'] ?? null) ? $overrides['twitter'] : []);
        $hreflang = $this->arrayOverride(
            $overrides,
            'hreflang',
            $this->hreflang($canonical),
        );
        $jsonLd = $this->arrayOverride($overrides, 'json_ld', [
            $this->serviceSchema($service, $seo),
            $this->breadcrumbSchema($service, $canonical),
        ]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'open_graph' => $openGraph,
            'twitter' => $twitter,
            'hreflang' => $hreflang,
            'json_ld' => $jsonLd,
        ];
    }

    /**
     * @return list<string>
     */
    public function normalizeKeywords(mixed $keywords): array
    {
        $values = is_array($keywords) ? $keywords : [$keywords];
        $normalized = [];

        foreach ($values as $value) {
            foreach (preg_split('/[,،\r\n]+/u', (string) $value) ?: [] as $keyword) {
                $keyword = Str::limit(trim($keyword), 60, '');

                if ($keyword === '') {
                    continue;
                }

                $key = mb_strtolower($keyword);
                $normalized[$key] ??= $keyword;
            }
        }

        return array_slice(array_values($normalized), 0, 20);
    }

    private function title(Model $content, bool $includeBrand = false): string
    {
        $title = trim((string) (
            $content->getAttribute('title')
            ?: $content->getAttribute('name')
            ?: config('app.name')
        ));

        if ($includeBrand) {
            $brand = $this->siteName();

            if ($brand !== '' && ! str_contains(mb_strtolower($title), mb_strtolower($brand))) {
                $title .= ' | '.$brand;
            }
        }

        return Str::limit($title, 70, '');
    }

    private function description(Model $content, string $fallback): string
    {
        $description = (string) (
            $content->getAttribute('excerpt')
            ?: $content->getAttribute('summary')
            ?: $content->getAttribute('description')
            ?: $fallback
        );
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags($description)) ?? $description);

        return Str::limit($description, 180, '');
    }

    private function siteName(): string
    {
        $brand = SiteSetting::query()->where('key', 'brand')->first()?->value;

        return is_array($brand) && filled($brand['name'] ?? null)
            ? (string) $brand['name']
            : (string) config('app.name');
    }

    private function isLocalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1';
    }

    /** @param array<string, mixed> $overrides */
    private function override(array $overrides, string $key, string $default): string
    {
        return filled($overrides[$key] ?? null)
            ? trim((string) $overrides[$key])
            : $default;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    private function arrayOverride(array $overrides, string $key, array $default): array
    {
        $value = $overrides[$key] ?? null;

        return is_array($value) && $value !== [] ? $value : $default;
    }

    /** @return array<string, string|null> */
    private function openGraph(Model $content, SeoMeta $seo): array
    {
        $heroMedia = $content instanceof Service
            ? $content->heroMedia()->first()
            : null;
        $image = $heroMedia instanceof Media ? $heroMedia->url() : null;

        return [
            'type' => 'website',
            'locale' => 'ar_SA',
            'site_name' => $this->siteName(),
            'title' => (string) $seo->title,
            'description' => (string) $seo->description,
            'url' => (string) $seo->canonical,
            'image' => $image,
        ];
    }

    /** @return list<array{lang: string, href: string}> */
    private function hreflang(string $canonical): array
    {
        $languages = SiteSetting::query()
            ->where('key', 'site_languages')
            ->first()?->value;
        $locales = is_array($languages) && is_array($languages['locales'] ?? null)
            ? $languages['locales']
            : ['ar-SA'];
        $alternates = collect($locales)
            ->filter(fn (mixed $locale): bool => filled($locale))
            ->map(fn (mixed $locale): array => [
                'lang' => (string) $locale,
                'href' => $canonical,
            ])
            ->values()
            ->all();
        $alternates[] = ['lang' => 'x-default', 'href' => $canonical];

        return $alternates;
    }

    /** @return array<string, mixed> */
    private function serviceSchema(Service $service, SeoMeta $seo): array
    {
        $contact = ContactSetting::query()->first();
        $heroMedia = $service->heroMedia()->first();
        $image = $heroMedia instanceof Media ? $heroMedia->url() : null;
        $faqs = $service->faqs()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['question', 'answer']);
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $service->title,
            'description' => (string) $seo->description,
            'url' => (string) $seo->canonical,
            'inLanguage' => 'ar-SA',
            'provider' => array_filter([
                '@type' => 'Organization',
                'name' => $this->siteName(),
                'telephone' => $contact?->phone,
                'email' => $contact?->email,
            ]),
            'image' => $image,
        ];

        if ($faqs->isNotEmpty()) {
            $schema['subjectOf'] = [
                '@type' => 'FAQPage',
                'mainEntity' => $faqs
                    ->filter(fn ($faq): bool => $faq instanceof Faq)
                    ->map(fn (Faq $faq): array => [
                        '@type' => 'Question',
                        'name' => $faq->question,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $faq->answer,
                        ],
                    ])->values()->all(),
            ];
        }

        return array_filter(
            $schema,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /** @return array<string, mixed> */
    private function breadcrumbSchema(Service $service, string $canonical): array
    {
        $origin = rtrim((string) config('app.production_url'), '/');
        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'الرئيسية',
                'item' => $origin.'/',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'الخدمات',
                'item' => $origin.'/services',
            ],
        ];

        $category = $service->getRelationValue('category');

        if ($category instanceof ServiceCategory) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $category->title,
                'item' => $origin.$category->routePath(),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => count($items) + 1,
            'name' => $service->title,
            'item' => $canonical,
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
