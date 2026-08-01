<?php

namespace App\Services;

class ServiceContentTemplates
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'essential' => 'خدمة مختصرة — مقدمة، مميزات، تواصل',
            'event' => 'تنظيم مناسبة — مقدمة، خطوات، معرض، أسئلة',
            'detailed' => 'خدمة تفصيلية — مقدمة، نص، مميزات، خطوات، تواصل',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function blocks(?string $template): array
    {
        return match ($template) {
            'essential' => [
                self::block('intro'),
                self::block('features', ['heading' => 'مميزات الخدمة', 'items' => []]),
                self::block('cta'),
            ],
            'event' => [
                self::block('intro'),
                self::block('steps', ['heading' => 'كيف ننفذ الخدمة؟', 'items' => []]),
                self::block('gallery', ['heading' => 'صور من أعمالنا', 'media_ids' => []]),
                self::block('faq', ['heading' => 'الأسئلة الشائعة', 'items' => []]),
                self::block('cta'),
            ],
            'detailed' => [
                self::block('intro'),
                self::block('text', ['heading' => 'عن الخدمة']),
                self::block('features', ['heading' => 'مميزات الخدمة', 'items' => []]),
                self::block('steps', ['heading' => 'خطوات التنفيذ', 'items' => []]),
                self::block('cta'),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function block(string $type, array $data = []): array
    {
        return [
            'type' => $type,
            ...$data,
        ];
    }
}
