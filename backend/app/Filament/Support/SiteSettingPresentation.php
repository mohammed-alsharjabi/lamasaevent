<?php

namespace App\Filament\Support;

class SiteSettingPresentation
{
    /**
     * @var array<string, array{title: string, location: string, group: string}>
     */
    private const SETTINGS = [
        'site_name' => [
            'title' => 'اسم الموقع في محركات البحث',
            'location' => 'يُستخدم كاسم افتراضي للموقع وفي بيانات SEO العامة.',
            'group' => 'الهوية وSEO',
        ],
        'site_description' => [
            'title' => 'وصف الموقع لمحركات البحث',
            'location' => 'يُستخدم وصفًا افتراضيًا عندما لا تكتب الصفحة وصف SEO خاصًا.',
            'group' => 'الهوية وSEO',
        ],
        'default_robots' => [
            'title' => 'السماح بالظهور في البحث',
            'location' => 'يتحكم في السماح لمحركات البحث بفهرسة الصفحات العامة.',
            'group' => 'الهوية وSEO',
        ],
        'brand' => [
            'title' => 'اسم وهوية الموقع',
            'location' => 'يظهر اسم الموقع ووصفه المختصر في الهيدر والفوتر.',
            'group' => 'الهوية',
        ],
        'footer' => [
            'title' => 'محتوى الفوتر',
            'location' => 'يظهر أسفل جميع صفحات الموقع. اترك بيانات المنفذ فارغة لإخفائها بالكامل.',
            'group' => 'الفوتر',
        ],
        'booking_form' => [
            'title' => 'نموذج طلب الحجز',
            'location' => 'تظهر هذه النصوص في نموذج الحجز الموجود قبل الفوتر.',
            'group' => 'الحجز',
        ],
        'booking_options' => [
            'title' => 'خيارات المناسبات في نموذج الحجز',
            'location' => 'تظهر داخل قائمة نوع المناسبة أو الخدمة في نموذج الحجز.',
            'group' => 'الحجز',
        ],
        'ui_labels' => [
            'title' => 'أسماء الأزرار العامة',
            'location' => 'تظهر على أزرار واتساب والاتصال والقائمة والتنقل في الموقع.',
            'group' => 'النصوص العامة',
        ],
    ];

    public static function title(?string $key): string
    {
        return self::SETTINGS[$key]['title'] ?? 'إعداد متقدم';
    }

    public static function location(?string $key): string
    {
        return self::SETTINGS[$key]['location']
            ?? 'إعداد تقني متقدم. لا تعدّله إلا إذا كنت تعرف مكان استخدامه.';
    }

    public static function group(?string $key): string
    {
        return self::SETTINGS[$key]['group'] ?? 'متقدم';
    }
}
