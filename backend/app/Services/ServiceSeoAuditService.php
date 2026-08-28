<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\SitemapEntry;
use Illuminate\Support\Str;

class ServiceSeoAuditService
{
    public function __construct(
        private readonly SeoDefaultsService $seoDefaults,
        private readonly ServiceDefaultsService $serviceDefaults,
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
    public function audit(array $state, ?Service $record = null): array
    {
        $service = $record ? clone $record : new Service;
        $recordStatus = $record?->getAttribute('status');
        $status = $state['status'] ?? (
            $recordStatus instanceof ContentStatus
                ? $recordStatus->value
                : ContentStatus::Draft->value
        );
        $storedOverrides = $record?->getAttribute('seo_overrides');
        $enteredTitle = trim((string) ($state['title'] ?? $record?->title));
        $enteredExcerpt = trim((string) ($state['excerpt'] ?? $record?->excerpt));
        $hasDescription = $enteredExcerpt !== ''
            || filled($state['meta_description_override'] ?? null);
        $service->forceFill([
            'title' => $enteredTitle !== '' ? $enteredTitle : 'اسم الخدمة',
            'excerpt' => $enteredExcerpt,
            'status' => $status,
            'service_category_id' => $state['service_category_id'] ?? $record?->service_category_id,
            'hero_media_id' => $state['hero_media_id'] ?? $record?->hero_media_id,
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

        $ignoreId = $record?->getKey() ? (int) $record->getKey() : null;
        $requestedSlug = Str::slug((string) ($state['slug_override'] ?? ''));
        $slugSource = $requestedSlug !== ''
            ? $requestedSlug
            : ((string) ($record?->slug ?: $service->title));
        $slug = $this->serviceDefaults->uniqueSlug($slugSource, $ignoreId);
        $service->slug = $slug;
        $service->slug_override = $requestedSlug !== '' ? $requestedSlug : null;
        $service->unsetRelation('heroMedia');
        $service->unsetRelation('category');
        $path = '/services/'.$slug;
        $values = $this->seoDefaults->previewService($service, $path);
        $image = $service->heroMedia()->first();

        if (! $image instanceof Media) {
            $image = null;
        }
        $checks = [];

        $this->check(
            $checks,
            $enteredTitle !== '',
            'عنوان الصفحة',
            $enteredTitle !== '' ? 'العنوان موجود وسيستخدم كـ H1.' : 'اكتب اسم الخدمة.',
        );
        $this->check(
            $checks,
            $hasDescription,
            'وصف نتيجة البحث',
            $hasDescription ? 'الوصف جاهز من الملخص أو التخصيص.' : 'أضف وصفًا مختصرًا واضحًا.',
        );
        $this->check(
            $checks,
            $image !== null,
            'الصورة البارزة',
            $image !== null ? 'الصورة ستستخدم في الصفحة والمشاركة.' : 'اختر صورة بارزة قبل النشر.',
        );
        $slugAvailable = $requestedSlug === '' || $requestedSlug === $slug;
        $this->check(
            $checks,
            $slugAvailable,
            'الرابط المختصر',
            $slugAvailable
                ? 'الرابط فريد: '.$slug
                : 'هذا الرابط مستخدم. اتركه تلقائيًا أو اختر قيمة أخرى.',
        );
        $duplicateTitle = SeoMeta::query()
            ->where('title', $values['title'])
            ->where('seoable_type', (new Service)->getMorphClass())
            ->when($ignoreId, fn ($query) => $query->where('seoable_id', '!=', $ignoreId))
            ->exists();
        $this->check(
            $checks,
            ! $duplicateTitle,
            'عنوان SEO',
            $duplicateTitle
                ? 'يوجد محتوى آخر بنفس عنوان SEO؛ خصص عنوانًا أوضح.'
                : 'عنوان SEO غير مكرر.',
        );
        $canonicalValid = filter_var($values['canonical'], FILTER_VALIDATE_URL) !== false;
        $this->check(
            $checks,
            $canonicalValid,
            'Canonical',
            $canonicalValid ? 'الرابط الأساسي صالح.' : 'قيمة Canonical المخصصة غير صالحة.',
        );
        $schemaValid = $this->schemaIsValid($values['json_ld']);
        $this->check(
            $checks,
            $schemaValid,
            'Schema',
            $schemaValid ? 'Service وBreadcrumbList صالحان.' : 'Schema المخصص يحتاج مراجعة.',
        );
        $alt = trim((string) ($service->hero_alt_override ?: $image?->alt ?: $service->title));
        $this->check(
            $checks,
            $image === null || $alt !== '',
            'وصف الصورة Alt',
            $image === null
                ? 'يُفحص بعد اختيار الصورة.'
                : ($alt !== '' ? 'وصف الصورة جاهز: '.$alt : 'أضف وصفًا للصورة.'),
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
            'Sitemap',
            $isPublished
                ? ($sitemapIncluded ? 'الصفحة مدرجة في Sitemap.' : 'ستدرج تلقائيًا عند النشر.')
                : 'المسودة مستبعدة من Sitemap والواجهة العامة.',
        );
        $draftRobotsSafe = $isPublished || str_contains((string) $values['robots'], 'noindex');
        $this->check(
            $checks,
            $draftRobotsSafe,
            'فهرسة المسودة',
            $draftRobotsSafe
                ? ($isPublished ? 'تعليمات الفهرسة مناسبة للنشر.' : 'المسودة تحمل noindex تلقائيًا.')
                : 'يجب ألا تسمح بفهرسة المسودة.',
        );

        $phrase = trim((string) $service->target_search_phrase);

        if ($phrase !== '') {
            $haystacks = [
                'العنوان' => $service->title,
                'الوصف' => $values['description'],
                'التفاصيل' => strip_tags((string) ($state['quick_details'] ?? '')),
            ];

            foreach ($haystacks as $label => $content) {
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
            'values' => $values + ['slug' => $slug, 'alt' => $alt],
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
        if (! is_array($schema) || $schema === [] || ! array_is_list($schema)) {
            return false;
        }

        foreach ($schema as $item) {
            if (
                ! is_array($item)
                || blank($item['@context'] ?? null)
                || blank($item['@type'] ?? null)
            ) {
                return false;
            }
        }

        return json_encode($schema) !== false;
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

        return is_array($decoded) ? $decoded : null;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
