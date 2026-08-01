<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\ContactSetting;
use App\Models\Service;
use App\Models\SiteSetting;

class ServiceDefaultsService
{
    public function apply(Service $service): void
    {
        if (! $service->uses_generated_defaults) {
            return;
        }

        if (! $service->exists && $service->getAttribute('sort_order') === null) {
            $service->sort_order = $this->nextSortOrder($service);
        }

        if ($this->status($service) === ContentStatus::Published && ! $service->published_at) {
            $service->published_at = now();
        }

        $cta = $this->cta($service);
        $service->cta_label = $cta['label'];
        $service->cta_url = $cta['url'];
        $service->whatsapp_enabled = $cta['enabled'];
        $service->setAttribute(
            'content_blocks',
            app(ServiceContentNormalizer::class)
                ->normalize($service->getAttribute('content_blocks')),
        );
    }

    /**
     * @return array{label: ?string, url: ?string, enabled: bool}
     */
    public function cta(Service $service): array
    {
        $overrides = $service->getAttribute('cta_overrides');

        if (! is_array($overrides)) {
            $overrides = [];
        }
        $contact = ContactSetting::query()->first();
        $ui = SiteSetting::query()->where('key', 'ui_labels')->first()?->value;
        $whatsapp = preg_replace('/\D+/', '', (string) $contact?->whatsapp) ?: '';
        $defaultLabel = is_array($ui) && filled($ui['whatsapp'] ?? null)
            ? (string) $ui['whatsapp']
            : 'تواصل عبر واتساب';
        $message = 'مرحبًا، أريد الاستفسار عن خدمة '.trim((string) $service->title);
        $defaultUrl = $whatsapp !== ''
            ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message)
            : null;

        return [
            'label' => filled($overrides['label'] ?? null)
                ? trim((string) $overrides['label'])
                : $defaultLabel,
            'url' => filled($overrides['url'] ?? null)
                ? trim((string) $overrides['url'])
                : $defaultUrl,
            'enabled' => match ($overrides['mode'] ?? 'auto') {
                'show' => true,
                'hide' => false,
                default => filled($defaultUrl),
            },
        ];
    }

    private function nextSortOrder(Service $service): int
    {
        return ((int) Service::query()
            ->where('service_category_id', $service->service_category_id)
            ->max('sort_order')) + 1;
    }

    private function status(Service $service): ContentStatus
    {
        $status = $service->status;

        return $status instanceof ContentStatus
            ? $status
            : ContentStatus::from((string) ($status ?: ContentStatus::Draft->value));
    }
}
