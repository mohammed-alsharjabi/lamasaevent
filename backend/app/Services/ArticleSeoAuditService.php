<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Media;
use App\Models\SeoMeta;
use App\Models\SitemapEntry;
use Illuminate\Support\Str;

class ArticleSeoAuditService
{
    public function __construct(
        private readonly SeoDefaultsService $seoDefaults,
        private readonly ArticleDefaultsService $articleDefaults,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *   ready: bool,
     *   status: string,
     *   values: array<string, mixed>,
     *   checks: list<array{ok: bool, label: string, message: string, severity: string}>
     * }
     */
    public function audit(array $state, ?Article $record = null): array
    {
        $article = $record ? clone $record : new Article;
        $recordStatus = $record?->getAttribute('status');
        $status = $state['status'] ?? (
            $recordStatus instanceof ContentStatus
                ? $recordStatus->value
                : ContentStatus::Draft->value
        );
        $storedOverrides = $record?->getAttribute('seo_overrides');
        $enteredTitle = trim((string) ($state['title'] ?? $record?->title));
        $enteredExcerpt = trim((string) ($state['excerpt'] ?? $record?->excerpt));
        $article->forceFill([
            'title' => $enteredTitle !== '' ? $enteredTitle : 'عنوان المقال',
            'excerpt' => $enteredExcerpt,
            'status' => $status,
            'article_category_id' => $state['article_category_id'] ?? $record?->article_category_id,
            'hero_media_id' => $state['hero_media_id'] ?? $record?->hero_media_id,
            'published_at' => $record?->published_at,
            'seo_title_override' => self::nullable($state['seo_title_override'] ?? null),
            'meta_description_override' => self::nullable($state['meta_description_override'] ?? null),
            'canonical_override' => self::nullable($state['canonical_override'] ?? null),
            'robots_override' => self::nullable($state['robots_override'] ?? null),
            'og_title_override' => self::nullable($state['og_title_override'] ?? null),
            'og_description_override' => self::nullable($state['og_description_override'] ?? null),
            'og_image_override' => self::nullable($state['og_image_override'] ?? null),
            'schema_override' => $this->jsonValue($state['schema_override'] ?? null),
            'target_search_phrase' => self::nullable($state['target_search_phrase'] ?? null),
            'hero_alt_override' => self::nullable($state['hero_alt_override'] ?? null),
            'seo_overrides' => is_array($state['seo_overrides'] ?? null)
                ? $state['seo_overrides']
                : (is_array($storedOverrides) ? $storedOverrides : []),
            'uses_generated_defaults' => true,
        ]);

        $category = ArticleCategory::query()->find($article->article_category_id);
        $article->setRelation('category', $category);
        $ignoreId = $record?->getKey() ? (int) $record->getKey() : null;
        $requestedSlug = Str::slug((string) ($state['slug_override'] ?? ''));
        $slugSource = $requestedSlug !== ''
            ? $requestedSlug
            : ((string) ($record?->slug ?: $article->title));
        $slug = $this->articleDefaults->uniqueSlug($slugSource, $ignoreId);
        $article->slug = $slug;
        $article->slug_override = $requestedSlug !== '' ? $requestedSlug : null;
        $path = '/blog/'.$slug;
        $values = $this->seoDefaults->previewArticle($article, $path);
        $image = $article->heroMedia()->first();
        $image = $image instanceof Media ? $image : null;
        $checks = [];

        $this->check(
            $checks,
            $enteredTitle !== '',
            'عنوان المقال',
            $enteredTitle !== '' ? 'العنوان موجود وسيظهر كعنوان رئيسي.' : 'اكتب عنوان المقال.',
        );
        $hasDescription = $enteredExcerpt !== ''
            || filled($state['meta_description_override'] ?? null);
        $this->check(
            $checks,
            $hasDescription,
            'وصف نتيجة البحث',
            $hasDescription ? 'الوصف جاهز من المقتطف أو التخصيص.' : 'أضف وصفًا مختصرًا للقارئ.',
        );
        $this->check(
            $checks,
            $image !== null,
            'الصورة البارزة',
            $image !== null ? 'الصورة جاهزة للمقال والمشاركة.' : 'اختر صورة بارزة قبل النشر.',
        );
        $slugAvailable = $requestedSlug === '' || $requestedSlug === $slug;
        $this->check(
            $checks,
            $slugAvailable,
            'رابط المقال',
            $slugAvailable ? 'الرابط فريد: '.$slug : 'الرابط مستخدم؛ اتركه تلقائيًا أو اختر قيمة أخرى.',
        );
        $duplicateTitle = SeoMeta::query()
            ->where('title', $values['title'])
            ->where('seoable_type', (new Article)->getMorphClass())
            ->when($ignoreId, fn ($query) => $query->where('seoable_id', '!=', $ignoreId))
            ->exists();
        $this->check(
            $checks,
            ! $duplicateTitle,
            'عنوان الظهور',
            $duplicateTitle ? 'يوجد مقال آخر بنفس عنوان الظهور.' : 'عنوان الظهور غير مكرر.',
        );
        $this->check(
            $checks,
            filter_var($values['canonical'], FILTER_VALIDATE_URL) !== false,
            'الرابط الأساسي',
            'يُنشأ الرابط الأساسي تلقائيًا من رابط المقال.',
        );
        $this->check(
            $checks,
            $this->schemaIsValid($values['json_ld']),
            'بيانات المقال لمحركات البحث',
            'BlogPosting ومسار التنقل يُنشآن تلقائيًا.',
        );
        $alt = trim((string) ($article->hero_alt_override ?: $image?->alt ?: $article->title));
        $this->check(
            $checks,
            $image === null || $alt !== '',
            'وصف الصورة',
            $image === null ? 'يُفحص بعد اختيار الصورة.' : 'النص البديل للصورة جاهز.',
        );

        $statusValue = $status instanceof ContentStatus ? $status->value : (string) $status;
        $isPublished = $statusValue === ContentStatus::Published->value;
        $sitemapIncluded = $record
            ? SitemapEntry::query()
                ->where('path', $record->routePath())
                ->where('is_included', true)
                ->exists()
            : false;
        $sitemapReady = $isPublished
            ? (! $record || $recordStatus !== ContentStatus::Published || $sitemapIncluded)
            : true;
        $this->check(
            $checks,
            $sitemapReady,
            'خريطة الموقع',
            $isPublished
                ? ($sitemapIncluded ? 'المقال مدرج في خريطة الموقع.' : 'سيُدرج تلقائيًا عند النشر.')
                : 'المسودة مستبعدة من الموقع ومحركات البحث.',
        );
        $draftRobotsSafe = $isPublished || str_contains((string) $values['robots'], 'noindex');
        $this->check(
            $checks,
            $draftRobotsSafe,
            'فهرسة المسودة',
            $isPublished ? 'تعليمات الفهرسة مناسبة للنشر.' : 'المسودة تحمل noindex تلقائيًا.',
        );

        $phrase = trim((string) $article->target_search_phrase);

        if ($phrase !== '') {
            foreach ([
                'العنوان' => $article->title,
                'الوصف' => $values['description'],
                'المحتوى' => strip_tags((string) ($state['quick_body'] ?? '')),
            ] as $label => $content) {
                $present = str_contains(mb_strtolower((string) $content), mb_strtolower($phrase));
                $checks[] = [
                    'ok' => $present,
                    'label' => 'عبارة البحث في '.$label,
                    'message' => $present
                        ? 'العبارة مستخدمة بصورة طبيعية.'
                        : 'اقتراح: اذكر «'.$phrase.'» بصورة طبيعية في '.$label.'.',
                    'severity' => 'suggestion',
                ];
            }
        }

        $ready = collect($checks)
            ->where('severity', 'error')
            ->every(fn (array $check): bool => $check['ok']);

        return [
            'ready' => $ready,
            'status' => $ready ? 'جاهز للنشر' : 'يحتاج مراجعة',
            'values' => $values,
            'checks' => $checks,
        ];
    }

    /**
     * @param  list<array{ok: bool, label: string, message: string, severity: string}>  $checks
     */
    private function check(
        array &$checks,
        bool $ok,
        string $label,
        string $message,
    ): void {
        $checks[] = compact('ok', 'label', 'message') + ['severity' => 'error'];
    }

    private function schemaIsValid(mixed $schema): bool
    {
        if (! is_array($schema) || $schema === []) {
            return false;
        }

        $schemas = array_is_list($schema) ? $schema : [$schema];
        $types = collect($schemas)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->pluck('@type');

        return $types->contains('BlogPosting') && $types->contains('BreadcrumbList');
    }

    private function jsonValue(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value === [] ? null : $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
