<?php

namespace App\Services;

use App\Models\ServiceCategory;
use Illuminate\Support\Str;

class ServiceContentSuggestionService
{
    /**
     * These are editable writing suggestions, never deterministic SEO fields
     * and never published automatically.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function suggest(array $state): array
    {
        $title = trim((string) ($state['title'] ?? 'الخدمة')) ?: 'الخدمة';
        $category = ServiceCategory::query()
            ->find($state['service_category_id'] ?? null)?->title;
        $context = filled($category) ? ' ضمن '.$category : '';
        $summary = trim(strip_tags((string) ($state['excerpt'] ?? '')));
        $summary = $summary !== ''
            ? $summary
            : "نقدم {$title}{$context} بتنسيق احترافي يهتم بالتفاصيل وجودة التنفيذ.";
        $description = Str::limit(
            trim(preg_replace('/\s+/u', ' ', strip_tags($summary)) ?? $summary),
            160,
            '',
        );

        return [
            'apply_title' => false,
            'suggested_title' => $title.' في الرياض',
            'apply_excerpt' => true,
            'suggested_excerpt' => $summary,
            'apply_meta_description' => true,
            'suggested_meta_description' => $description,
            'apply_intro' => true,
            'suggested_intro' => '<p>'.e($summary)
                .' نراجع احتياج المناسبة والموقع والوقت قبل اعتماد تفاصيل التنفيذ.</p>',
            'apply_features' => true,
            'suggested_features' => [
                ['title' => 'تنسيق مناسب للموقع', 'description' => 'تكييف التنفيذ مع مساحة الموقع وطبيعة المناسبة.'],
                ['title' => 'اهتمام بالتفاصيل', 'description' => 'مراجعة العناصر الأساسية قبل موعد التنفيذ.'],
                ['title' => 'تواصل واضح', 'description' => 'تأكيد المتطلبات والموعد وخطوات التجهيز مسبقًا.'],
            ],
            'apply_faqs' => true,
            'suggested_faqs' => [
                [
                    'question' => 'كيف أطلب '.$title.'؟',
                    'answer' => 'أرسل تاريخ المناسبة وموقعها والمتطلبات الأساسية عبر واتساب للحصول على التفاصيل المناسبة.',
                ],
                [
                    'question' => 'هل يمكن تخصيص تفاصيل الخدمة؟',
                    'answer' => 'نعم، يتم تحديد التفاصيل بحسب مساحة الموقع وطبيعة المناسبة والميزانية المتاحة.',
                ],
            ],
            'apply_alt' => true,
            'suggested_alt' => $title.' من أعمال لمسة التميز',
        ];
    }
}
