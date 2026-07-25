<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Models\Article;
use App\Models\SeoMeta;
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
        $title = $this->title($content);
        $description = $this->description($content, $title);
        $canonical = rtrim((string) config('app.production_url'), '/').$path;

        if (blank($seo->title)) {
            $seo->title = $title;
        }

        if (blank($seo->description)) {
            $seo->description = $description;
        }

        if (
            blank($seo->canonical)
            || (
                blank($content->getAttribute('legacy_path'))
                && $this->isLocalUrl((string) $seo->canonical)
            )
        ) {
            $seo->canonical = $canonical;
        }

        if (blank($seo->robots)) {
            $seo->robots = 'index, follow';
        }

        $seo->setAttribute(
            'keywords',
            $this->normalizeKeywords($seo->getAttribute('keywords')),
        );

        if (blank($seo->getAttribute('open_graph'))) {
            $seo->setAttribute('open_graph', [
                'type' => $content instanceof Article ? 'article' : 'website',
                'locale' => 'ar_SA',
                'site_name' => $this->siteName(),
                'title' => (string) $seo->title,
                'description' => (string) $seo->description,
                'url' => (string) $seo->canonical,
            ]);
        }

        if (blank($seo->getAttribute('twitter'))) {
            $seo->setAttribute('twitter', [
                'card' => 'summary',
                'title' => (string) $seo->title,
                'description' => (string) $seo->description,
            ]);
        }

        if (blank($seo->getAttribute('hreflang'))) {
            $seo->setAttribute('hreflang', [
                ['lang' => 'ar-SA', 'href' => (string) $seo->canonical],
                ['lang' => 'x-default', 'href' => (string) $seo->canonical],
            ]);
        }

        if ($seo->getAttribute('json_ld') === null) {
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

    private function title(Model $content): string
    {
        $title = trim((string) (
            $content->getAttribute('title')
            ?: $content->getAttribute('name')
            ?: config('app.name')
        ));

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
}
