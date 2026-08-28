<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Models\Area;
use App\Models\Article;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PublicPageRenderer
{
    public const CACHE_KEY = 'public-page:content:v1';

    /** @var list<string> */
    private const LISTING_PATHS = ['/', '/services', '/blog', '/areas', '/gallery'];

    public function __construct(
        private readonly ContentExportService $exporter,
        private readonly ContentSnapshotWriter $writer,
    ) {}

    public function isListingPath(string $path): bool
    {
        return in_array($path, self::LISTING_PATHS, true);
    }

    public function render(Request $request, string $path, ?Model $content): string
    {
        $html = $this->readStaticShell($request, $path);
        $payload = $this->payload();

        if ($content instanceof ManagedContent && $content->isPublished()) {
            $entity = $this->entityFor($payload, $content);

            if (is_array($entity)) {
                $html = $this->replaceSeo($html, (array) ($entity['seo_meta'] ?? []));

                if (! $this->isListingPath($path)) {
                    $html = $this->replaceMain(
                        $html,
                        view('public.managed-content', [
                            'entity' => $entity,
                            'kind' => $this->kindFor($content),
                            'related' => $this->relatedEntities($payload, $entity, $content),
                        ])->render(),
                    );
                }
            }
        }

        $html = $this->refreshGlobals($html, $payload);

        return $this->refreshListings($html, $path, $payload);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $json = Cache::remember(
            self::CACHE_KEY,
            now()->addDay(),
            fn (): string => $this->writer->encode($this->exporter->build()),
        );

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('The current public content snapshot is invalid.');
        }

        return $payload;
    }

    private function readStaticShell(Request $request, string $path): string
    {
        $configuredRoot = config('public_delivery.static_root');
        $candidates = [
            is_string($configuredRoot) ? $configuredRoot : null,
            $request->server('DOCUMENT_ROOT'),
            base_path('../frontend/dist'),
        ];
        $root = collect($candidates)
            ->filter(fn (mixed $candidate): bool => is_string($candidate) && $candidate !== '')
            ->map(fn (string $candidate): string|false => realpath($candidate))
            ->first(fn (string|false $candidate): bool => is_string($candidate) && is_file($candidate.'/index.html'));

        if (! is_string($root)) {
            throw new RuntimeException('The verified public frontend is unavailable.');
        }

        $relative = trim($path, '/');
        $pageFile = $relative === ''
            ? $root.'/index.html'
            : $root.'/'.$relative.'/index.html';
        $file = is_file($pageFile) ? $pageFile : $root.'/index.html';
        $html = file_get_contents($file);

        if (! is_string($html) || ! str_contains($html, '<html')) {
            throw new RuntimeException('The verified public page shell is invalid.');
        }

        return $html;
    }

    /** @param array<string, mixed> $payload */
    private function entityFor(array $payload, Model&ManagedContent $content): ?array
    {
        $collection = $payload[$this->payloadKeyFor($content)] ?? [];

        foreach (is_array($collection) ? $collection : [] as $entity) {
            if (is_array($entity) && (int) ($entity['id'] ?? 0) === (int) $content->getKey()) {
                return $entity;
            }
        }

        return null;
    }

    private function payloadKeyFor(Model&ManagedContent $content): string
    {
        return match (true) {
            $content instanceof Service => 'services',
            $content instanceof ServiceCategory => 'service_categories',
            $content instanceof Article => 'articles',
            $content instanceof Area => 'areas',
            $content instanceof Page => 'pages',
            default => throw new RuntimeException('Unsupported public content type.'),
        };
    }

    private function kindFor(Model&ManagedContent $content): string
    {
        return match (true) {
            $content instanceof Service => 'service',
            $content instanceof ServiceCategory => 'service-category',
            $content instanceof Article => 'article',
            $content instanceof Area => 'area',
            default => 'page',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $entity
     * @return list<array<string, mixed>>
     */
    private function relatedEntities(
        array $payload,
        array $entity,
        Model&ManagedContent $content,
    ): array {
        $services = array_values(array_filter(
            (array) ($payload['services'] ?? []),
            fn (mixed $service): bool => is_array($service),
        ));

        if ($content instanceof ServiceCategory) {
            return array_values(array_filter(
                $services,
                fn (array $service): bool => (int) ($service['service_category_id'] ?? 0) === (int) ($entity['id'] ?? 0),
            ));
        }

        if ($content instanceof Service) {
            return array_values(array_filter(
                $services,
                fn (array $service): bool => (int) data_get($service, 'parent.id', 0) === (int) ($entity['id'] ?? 0),
            ));
        }

        return [];
    }

    /** @param array<string, mixed> $seo */
    private function replaceSeo(string $html, array $seo): string
    {
        $title = $this->escape($seo['title'] ?? '');
        $description = $this->escape($seo['description'] ?? '');
        $robots = $this->escape($seo['robots'] ?? 'index,follow');
        $canonical = $this->escape($seo['canonical'] ?? '');
        $keywords = collect((array) ($seo['keywords'] ?? []))
            ->filter(fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== '')
            ->implode(', ');

        $html = $this->replaceOrInsert(
            $html,
            '~<title\b[^>]*>.*?</title>~is',
            '<title>'.$title.'</title>',
        );
        $html = $this->replaceMeta($html, 'name', 'description', $description);
        $html = $this->replaceMeta($html, 'name', 'robots', $robots);
        $html = $this->replaceLink($html, 'canonical', $canonical);

        if ($keywords !== '') {
            $html = $this->replaceMeta($html, 'name', 'keywords', $this->escape($keywords));
        } else {
            $html = preg_replace('~\s*<meta\b[^>]*\bname=["\']keywords["\'][^>]*>~i', '', $html) ?? $html;
        }

        foreach ((array) ($seo['open_graph'] ?? []) as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $property = match ((string) $key) {
                    'image_alt' => 'image:alt',
                    'site_name' => 'site_name',
                    default => (string) $key,
                };

                $html = $this->replaceMeta(
                    $html,
                    'property',
                    'og:'.$property,
                    $this->escape($value),
                );
            }
        }

        foreach ((array) ($seo['twitter'] ?? []) as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $html = $this->replaceMeta(
                    $html,
                    'name',
                    'twitter:'.str_replace('_', ':', (string) $key),
                    $this->escape($value),
                );
            }
        }

        $html = preg_replace(
            '~\s*<link\b[^>]*\brel=["\']alternate["\'][^>]*\bhreflang=["\'][^"\']+["\'][^>]*>~i',
            '',
            $html,
        ) ?? $html;
        $alternates = collect((array) ($seo['hreflang'] ?? []))
            ->filter(fn (mixed $alternate): bool => is_array($alternate))
            ->map(fn (array $alternate): string => sprintf(
                '<link rel="alternate" hreflang="%s" href="%s">',
                $this->escape($alternate['lang'] ?? ''),
                $this->escape($alternate['href'] ?? $alternate['url'] ?? ''),
            ))
            ->implode('');

        $html = preg_replace(
            '~\s*<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>~is',
            '',
            $html,
        ) ?? $html;
        $schemas = collect((array) ($seo['json_ld'] ?? []))
            ->filter(fn (mixed $schema): bool => is_array($schema))
            ->map(fn (array $schema): string => '<script type="application/ld+json">'.
                json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).
                '</script>')
            ->implode('');

        return $this->insertBeforeHeadEnd($html, $alternates.$schemas);
    }

    private function replaceMeta(
        string $html,
        string $attribute,
        string $key,
        string $value,
    ): string {
        $pattern = sprintf(
            '~<meta\b(?=[^>]*\b%s=["\']%s["\'])[^>]*>~i',
            preg_quote($attribute, '~'),
            preg_quote($key, '~'),
        );

        return $this->replaceOrInsert(
            $html,
            $pattern,
            sprintf('<meta %s="%s" content="%s">', $attribute, $key, $value),
        );
    }

    private function replaceLink(string $html, string $rel, string $href): string
    {
        return $this->replaceOrInsert(
            $html,
            '~<link\b(?=[^>]*\brel=["\']'.preg_quote($rel, '~').'["\'])[^>]*>~i',
            '<link rel="'.$rel.'" href="'.$href.'">',
        );
    }

    private function replaceOrInsert(string $html, string $pattern, string $replacement): string
    {
        if (preg_match($pattern, $html) === 1) {
            return preg_replace_callback($pattern, fn (): string => $replacement, $html, 1) ?? $html;
        }

        return $this->insertBeforeHeadEnd($html, $replacement);
    }

    private function insertBeforeHeadEnd(string $html, string $markup): string
    {
        if ($markup === '') {
            return $html;
        }

        return preg_replace_callback(
            '~</head>~i',
            fn (): string => $markup.'</head>',
            $html,
            1,
        ) ?? $html;
    }

    private function replaceMain(string $html, string $main): string
    {
        $pattern = '~<main\b(?=[^>]*\bid=["\']main["\'])[^>]*>.*?</main>~is';

        if (preg_match($pattern, $html) === 1) {
            return preg_replace_callback($pattern, fn (): string => $main, $html, 1) ?? $html;
        }

        return preg_replace_callback(
            '~<body\b[^>]*>~i',
            fn (array $match): string => $match[0].$main,
            $html,
            1,
        ) ?? $html;
    }

    /** @param array<string, mixed> $payload */
    private function refreshGlobals(string $html, array $payload): string
    {
        $settings = is_array($payload['settings'] ?? null)
            ? $payload['settings']
            : [];
        $contact = is_array($payload['contact'] ?? null)
            ? $payload['contact']
            : [];
        $brand = is_array($settings['brand'] ?? null)
            ? $settings['brand']
            : [];
        $footer = is_array($settings['footer'] ?? null)
            ? $settings['footer']
            : [];
        $ui = is_array($settings['ui_labels'] ?? null)
            ? $settings['ui_labels']
            : [];
        $form = is_array($settings['booking_form'] ?? null)
            ? $settings['booking_form']
            : [];
        $options = is_array($settings['booking_options'] ?? null)
            ? $settings['booking_options']
            : [];

        $headerItems = $this->menuItems($payload, 'header');
        if (count($headerItems) >= 2) {
            $html = $this->replaceMarkedRegion(
                $html,
                'header',
                view('public.fragments.header', [
                    'brand' => $brand,
                    'ui' => $ui,
                    'items' => collect($headerItems),
                ])->render(),
            );
        }

        $html = $this->replaceMarkedRegion(
            $html,
            'footer',
            view('public.fragments.footer', [
                'brand' => $brand,
                'contact' => $contact,
                'footer' => $footer,
                'ui' => $ui,
                'items' => $this->menuItems($payload, 'footer'),
            ])->render(),
        );
        $html = $this->replaceMarkedRegion(
            $html,
            'booking-form',
            view('public.fragments.booking-form', [
                'contact' => $contact,
                'form' => $form,
                'options' => $options,
            ])->render(),
        );

        return $this->refreshContactLinks($html, $contact, $brand, $ui);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function menuItems(array $payload, string $location): array
    {
        foreach ((array) ($payload['menus'] ?? []) as $menu) {
            if (
                ! is_array($menu)
                || ($menu['location'] ?? null) !== $location
                || ! ($menu['is_active'] ?? false)
            ) {
                continue;
            }

            $items = array_values(array_filter(
                (array) ($menu['all_items'] ?? []),
                fn (mixed $item): bool => is_array($item) && ($item['is_active'] ?? false),
            ));
            usort(
                $items,
                fn (array $left, array $right): int => [
                    (int) ($left['sort_order'] ?? 0),
                    (int) ($left['id'] ?? 0),
                ] <=> [
                    (int) ($right['sort_order'] ?? 0),
                    (int) ($right['id'] ?? 0),
                ],
            );

            return $items;
        }

        return [];
    }

    private function replaceMarkedRegion(
        string $html,
        string $marker,
        string $markup,
    ): string {
        if ($markup === '') {
            return $html;
        }

        $pattern = '~<!--cms-live:'.preg_quote($marker, '~').':start-->.*?<!--cms-live:'.preg_quote($marker, '~').':end-->~s';

        if (preg_match($pattern, $html) !== 1) {
            return $html;
        }

        $region = '<!--cms-live:'.$marker.':start-->'.$markup.'<!--cms-live:'.$marker.':end-->';

        return preg_replace_callback($pattern, fn (): string => $region, $html, 1) ?? $html;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  array<string, mixed>  $brand
     * @param  array<string, mixed>  $ui
     */
    private function refreshContactLinks(
        string $html,
        array $contact,
        array $brand,
        array $ui,
    ): string {
        $phone = trim((string) ($contact['phone'] ?? ''));
        $phoneDisplay = trim((string) ($contact['phone_display'] ?? $phone));
        $whatsapp = preg_replace('/\D+/', '', (string) ($contact['whatsapp'] ?? '')) ?? '';
        $email = trim((string) ($contact['email'] ?? ''));

        if ($phone !== '') {
            $html = preg_replace(
                "~\\bhref=([\"'])tel:[^\"']*\\1~i",
                'href="'.$this->escape('tel:'.$phone).'"',
                $html,
            ) ?? $html;
            $html = $this->replaceClassText($html, 'site-footer__phone', $phoneDisplay);
            $html = $this->replaceClassText($html, 'cta-call__num', $phoneDisplay);
        }

        if ($whatsapp !== '') {
            $html = preg_replace(
                '~https://wa\.me/[0-9+]+~i',
                'https://wa.me/'.$whatsapp,
                $html,
            ) ?? $html;
        }

        if ($email !== '') {
            $html = preg_replace(
                "~\\bhref=([\"'])mailto:[^\"']*\\1~i",
                'href="mailto:'.$this->escape($email).'"',
                $html,
            ) ?? $html;
        }

        $brandName = trim((string) ($brand['name'] ?? ''));
        $whatsappLabel = trim((string) ($ui['whatsapp'] ?? 'واتساب'));
        $phoneLabel = trim((string) ($ui['phone_call'] ?? 'اتصال هاتفي'));
        $arabicPhone = strtr($phoneDisplay, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);

        $html = $this->replaceNestedClassText(
            $html,
            'float-dock__fab--wa',
            $whatsappLabel.' +'.$whatsapp.' — '.$brandName,
        );

        return $this->replaceNestedClassText(
            $html,
            'float-dock__fab--call',
            $phoneLabel.' '.$phone.' — '.$arabicPhone,
        );
    }

    private function replaceClassText(string $html, string $className, string $text): string
    {
        $pattern = "~(<[^>]+\\bclass=([\"'])[^\"']*\\b".preg_quote($className, '~')."\\b[^\"']*\\2[^>]*>).*?(</[^>]+>)~is";

        return preg_replace_callback(
            $pattern,
            fn (array $match): string => $match[1].$this->escape($text).$match[3],
            $html,
        ) ?? $html;
    }

    private function replaceNestedClassText(string $html, string $className, string $text): string
    {
        $pattern = "~(<a\\b[^>]*\\bclass=([\"'])[^\"']*\\b".preg_quote($className, '~')."\\b[^\"']*\\2[^>]*>.*?<span\\b[^>]*\\bclass=([\"'])[^\"']*\\bvisually-hidden\\b[^\"']*\\3[^>]*>).*?(</span>)~is";

        return preg_replace_callback(
            $pattern,
            fn (array $match): string => $match[1].$this->escape($text).$match[4],
            $html,
            1,
        ) ?? $html;
    }

    /** @param array<string, mixed> $payload */
    private function refreshListings(string $html, string $path, array $payload): string
    {
        if ($path === '/') {
            return $this->replaceSlot(
                $html,
                'home-services',
                collect($this->managed($payload['services'] ?? []))
                    ->map(fn (array $service): string => $this->homeServiceCard($service))
                    ->implode(''),
                'ph-services__grid',
            );
        }

        if ($path === '/services') {
            $categories = collect($this->managed($payload['service_categories'] ?? []))
                ->map(fn (array $category): string => $this->servicesCard($category, 'service-category'));
            $services = collect($this->managed($payload['services'] ?? []))
                ->map(fn (array $service): string => $this->servicesCard($service, 'service'));

            return $this->replaceSlot(
                $html,
                'services-index',
                $categories->concat($services)->implode(''),
                'svx-grid',
            );
        }

        if ($path === '/blog') {
            return $this->replaceSlot(
                $html,
                'articles-index',
                collect($this->managed($payload['articles'] ?? []))
                    ->map(fn (array $article): string => $this->articleCard($article))
                    ->implode(''),
                'blog-index__grid',
            );
        }

        if ($path === '/areas') {
            return $this->replaceSlot(
                $html,
                'areas-index',
                collect($this->managed($payload['areas'] ?? []))
                    ->map(fn (array $area): string => $this->areaCard($area))
                    ->implode(''),
                'areas-hub__grid',
            );
        }

        if ($path === '/gallery') {
            return $this->replaceSlot(
                $html,
                'galleries-index',
                collect($this->managedGalleries($payload['galleries'] ?? []))
                    ->map(fn (array $gallery): string => $this->gallerySection($gallery))
                    ->implode(''),
                'gallery-page__footnote',
                true,
            );
        }

        return $html;
    }

    /** @return list<array<string, mixed>> */
    private function managed(mixed $entities): array
    {
        return array_values(array_filter(
            is_array($entities) ? $entities : [],
            fn (mixed $entity): bool => is_array($entity) && ($entity['legacy_path'] ?? null) === null,
        ));
    }

    /** @return list<array<string, mixed>> */
    private function managedGalleries(mixed $galleries): array
    {
        return array_values(array_filter(
            is_array($galleries) ? $galleries : [],
            fn (mixed $gallery): bool => is_array($gallery) && ! ($gallery['is_legacy'] ?? false),
        ));
    }

    private function replaceSlot(
        string $html,
        string $marker,
        string $markup,
        string $containerClass,
        bool $beforeContainer = false,
    ): string {
        $slot = '<!--cms-native:'.$marker.':start-->'.$markup.'<!--cms-native:'.$marker.':end-->';
        $pattern = '~<!--cms-native:'.preg_quote($marker, '~').':start-->.*?<!--cms-native:'.preg_quote($marker, '~').':end-->~s';

        if (preg_match($pattern, $html) === 1) {
            return preg_replace_callback($pattern, fn (): string => $slot, $html, 1) ?? $html;
        }

        return $this->injectAtClass($html, $containerClass, $slot, $beforeContainer);
    }

    private function injectAtClass(
        string $html,
        string $className,
        string $markup,
        bool $before,
    ): string {
        preg_match_all(
            '~<([a-z][\w:-]*)\b[^>]*\bclass=(["\'])(.*?)\2[^>]*>~is',
            $html,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        foreach ($matches as $match) {
            $classes = preg_split('/\s+/', trim($match[3][0])) ?: [];

            if (! in_array($className, $classes, true)) {
                continue;
            }

            $opening = $match[0][0];
            $openingOffset = $match[0][1];

            if ($before) {
                return substr($html, 0, $openingOffset).$markup.substr($html, $openingOffset);
            }

            $tag = preg_quote($match[1][0], '~');
            $contentStart = $openingOffset + strlen($opening);
            preg_match_all(
                '~</?'.$tag.'\b[^>]*>~i',
                substr($html, $contentStart),
                $tags,
                PREG_OFFSET_CAPTURE,
            );
            $depth = 1;

            foreach ($tags[0] as [$candidate, $offset]) {
                $depth += str_starts_with($candidate, '</') ? -1 : 1;

                if ($depth === 0) {
                    $insertion = $contentStart + $offset;

                    return substr($html, 0, $insertion).$markup.substr($html, $insertion);
                }
            }
        }

        return $html;
    }

    /** @param array<string, mixed> $entity */
    private function homeServiceCard(array $entity): string
    {
        return '<a class="ph-svc-card" href="'.$this->path($entity).'" data-cms-additions data-cms-native-entry data-cms-kind="service" data-cms-id="'.(int) ($entity['id'] ?? 0).'">'
            .'<div class="ph-svc-card__media">'.$this->image($entity, 800, 550).'<div class="ph-svc-card__veil" aria-hidden="true"></div>'
            .'<h3 class="ph-svc-card__title">'.$this->escape($entity['title'] ?? '').'</h3></div>'
            .'<div class="ph-svc-card__body"><p class="ph-svc-card__desc">'.$this->escape($this->description($entity)).'</p>'
            .'<span class="ph-svc-card__more">عرض التفاصيل</span></div></a>';
    }

    /** @param array<string, mixed> $entity */
    private function servicesCard(array $entity, string $kind): string
    {
        return '<li data-cms-additions data-cms-native-entry data-cms-kind="'.$kind.'" data-cms-id="'.(int) ($entity['id'] ?? 0).'">'
            .'<a class="svx-card" href="'.$this->path($entity).'"><div class="svx-card__media">'.$this->image($entity, 640, 800)
            .'<div class="svx-card__veil" aria-hidden="true"></div><div class="svx-card__body">'
            .'<h2 class="svx-card__h2">'.$this->escape($entity['title'] ?? '').'</h2>'
            .'<p class="svx-card__line">'.$this->escape($this->description($entity)).'</p>'
            .'<span class="svx-card__cta">عرض التفاصيل</span></div></div></a></li>';
    }

    /** @param array<string, mixed> $entity */
    private function articleCard(array $entity): string
    {
        return '<article class="blog-card" data-cms-additions data-cms-native-entry data-cms-kind="article" data-cms-id="'.(int) ($entity['id'] ?? 0).'">'
            .'<a class="blog-card__link" href="'.$this->path($entity).'"><div class="blog-card__media">'.$this->image($entity, 800, 500)
            .'<div class="blog-card__veil" aria-hidden="true"></div></div><div class="blog-card__body">'
            .'<span class="blog-card__topic">'.$this->escape($entity['topic'] ?? 'مقالات لمسة').'</span>'
            .'<h2 class="blog-card__title">'.$this->escape($entity['title'] ?? '').'</h2>'
            .'<p class="blog-card__excerpt">'.$this->escape($this->description($entity)).'</p>'
            .'<span class="blog-card__cta">قراءة المقال</span></div></a></article>';
    }

    /** @param array<string, mixed> $entity */
    private function areaCard(array $entity): string
    {
        return '<li data-cms-additions data-cms-native-entry data-cms-kind="area" data-cms-id="'.(int) ($entity['id'] ?? 0).'">'
            .'<a class="areas-hub__card" href="'.$this->path($entity).'">'
            .'<h2 class="areas-hub__h2">'.$this->escape($entity['title'] ?? '').'</h2>'
            .'<p class="areas-hub__desc">'.$this->escape($this->description($entity)).'</p>'
            .'<span class="areas-hub__more">التفاصيل</span></a></li>';
    }

    /** @param array<string, mixed> $gallery */
    private function gallerySection(array $gallery): string
    {
        $items = collect((array) ($gallery['items'] ?? []))
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['is_active'] ?? false) && filled(data_get($item, 'media.public_url')))
            ->map(function (array $item): string {
                $media = (array) ($item['media'] ?? []);

                return '<figure class="gallery-portfolio__cell"><img src="'.$this->escape($media['public_url'] ?? '').'" alt="'
                    .$this->escape($item['alt'] ?? $media['alt'] ?? $item['title'] ?? '').'" loading="lazy" decoding="async"></figure>';
            })
            ->implode('');

        return '<section class="gallery-portfolio__section" data-cms-additions data-cms-native-entry data-cms-kind="gallery" data-cms-id="'.(int) ($gallery['id'] ?? 0).'">'
            .'<header class="gallery-portfolio__head"><p class="gallery-portfolio__kicker">معرض الأعمال</p>'
            .'<h2 class="gallery-portfolio__title">'.$this->escape($gallery['title'] ?? '').'</h2>'
            .'<p class="gallery-portfolio__lead">'.$this->escape($gallery['description'] ?? '').'</p></header>'
            .'<div class="gallery-portfolio__grid">'.$items.'</div></section>';
    }

    /** @param array<string, mixed> $entity */
    private function image(array $entity, int $width, int $height): string
    {
        $hero = (array) ($entity['hero_media_summary'] ?? []);
        $url = $hero['public_url'] ?? null;

        if (! is_string($url) || $url === '') {
            return '';
        }

        return '<img src="'.$this->escape($url).'" alt="'.$this->escape($hero['alt'] ?? $entity['title'] ?? '').'" width="'.$width.'" height="'.$height.'" loading="lazy" decoding="async">';
    }

    /** @param array<string, mixed> $entity */
    private function path(array $entity): string
    {
        $path = $entity['public_path'] ?? null;

        return $this->escape(is_string($path) && str_starts_with($path, '/') ? $path : '/');
    }

    /** @param array<string, mixed> $entity */
    private function description(array $entity): string
    {
        foreach (['excerpt', 'summary'] as $key) {
            if (is_string($entity[$key] ?? null) && trim($entity[$key]) !== '') {
                return trim($entity[$key]);
            }
        }

        return '';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
