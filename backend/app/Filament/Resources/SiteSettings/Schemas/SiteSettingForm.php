<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use App\Filament\Support\SiteSettingPresentation;
use App\Models\SiteSetting;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(
                    fn (?SiteSetting $record): string => SiteSettingPresentation::title(
                        $record?->key,
                    ),
                )
                    ->description(
                        fn (?SiteSetting $record): string => SiteSettingPresentation::location(
                            $record?->key,
                        ).' بعد الحفظ يبدأ تحديث الموقع تلقائيًا.',
                    )
                    ->columns(2)
                    ->schema(
                        fn (?SiteSetting $record): array => self::fieldsFor(
                            (string) $record?->key,
                        ),
                    )
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    /**
     * @return array<int, mixed>
     */
    private static function fieldsFor(string $key): array
    {
        return match ($key) {
            'site_name' => [
                self::text(
                    'value.text',
                    'اسم الموقع',
                    'يظهر كاسم افتراضي في محركات البحث وعند مشاركة الموقع.',
                )->required()->columnSpanFull(),
            ],
            'site_description' => [
                self::textarea(
                    'value.text',
                    'الوصف الافتراضي للموقع',
                    'اكتب جملة واضحة تلخص النشاط. تُستخدم فقط عندما لا تملك الصفحة وصفًا خاصًا.',
                )->maxLength(180)->columnSpanFull(),
            ],
            'default_robots' => [
                Select::make('value.text')
                    ->label('هل تسمح لمحركات البحث بإظهار الموقع؟')
                    ->options([
                        'index, follow, max-image-preview:large' => 'نعم، اسمح بالظهور وتتبع الروابط',
                        'noindex, nofollow' => 'لا، امنع الظهور مؤقتًا',
                    ])
                    ->helperText('الخيار الموصى به بعد إطلاق الموقع هو السماح بالظهور.')
                    ->required()
                    ->columnSpanFull(),
            ],
            'brand' => [
                self::text(
                    'value.name',
                    'اسم العلامة',
                    'يظهر بجانب الشعار وفي الفوتر.',
                )->required(),
                self::text(
                    'value.tagline',
                    'الوصف القصير تحت الاسم',
                    'مثال: تنسيق مناسبات — الرياض.',
                ),
                Hidden::make('value.logo_src'),
                Hidden::make('value.logo_width'),
                Hidden::make('value.logo_height'),
                Hidden::make('value.favicon_src'),
            ],
            'footer' => self::footerFields(),
            'booking_form' => self::bookingFormFields(),
            'booking_options' => [
                KeyValue::make('value')
                    ->label('أنواع المناسبات المتاحة')
                    ->helperText('يمينًا: القيمة التي تُرسل مع الطلب. يسارًا: النص الذي يراه العميل في القائمة.')
                    ->keyLabel('القيمة المرسلة')
                    ->valueLabel('النص الظاهر للعميل')
                    ->addActionLabel('إضافة خيار')
                    ->reorderable()
                    ->columnSpanFull(),
            ],
            'ui_labels' => [
                self::text('value.whatsapp', 'اسم زر واتساب')->required(),
                self::text('value.call', 'اسم زر الاتصال')->required(),
                self::text('value.phone_call', 'وصف الاتصال لقارئ الشاشة')->required(),
                self::text('value.menu_open', 'وصف فتح القائمة')->required(),
                self::text('value.menu_close', 'وصف إغلاق القائمة')->required(),
                self::text('value.primary_navigation', 'وصف القائمة الرئيسية')->required(),
                self::text('value.quick_contact_channels', 'وصف أزرار التواصل السريع')->required(),
                self::text('value.skip_to_content', 'رابط تخطي القائمة')->required(),
            ],
            default => [
                KeyValue::make('value')
                    ->label('قيم الإعداد المتقدم')
                    ->helperText('هذا إعداد تقني. عدّله فقط إذا كنت تعرف مكان استخدامه.')
                    ->keyLabel('الاسم البرمجي')
                    ->valueLabel('القيمة')
                    ->columnSpanFull(),
            ],
        };
    }

    /**
     * @return array<int, mixed>
     */
    private static function footerFields(): array
    {
        return [
            self::textarea(
                'value.description',
                'وصف النشاط في الفوتر',
                'يظهر تحت اسم الموقع في أول عمود من الفوتر.',
            )->rows(3)->columnSpanFull(),
            self::text(
                'value.company_line',
                'السطر المختصر بجانب البريد',
                'مثال: لمسة التميز · الرياض ·',
            )->columnSpanFull(),
            self::text('value.quick_links_heading', 'عنوان عمود الروابط السريعة')->required(),
            self::text('value.contact_heading', 'عنوان عمود التواصل')->required(),
            self::text(
                'value.legal',
                'سطر الحقوق',
                'يظهر في آخر الفوتر، مثل © 2026 اسم المنشأة.',
            )->columnSpanFull(),
            Section::make('اسم منفذ الموقع أو النسبة — اختياري')
                ->description('إذا تركت هذه الحقول فارغة يختفي هذا الجزء كاملًا من الفوتر، دون ترك مساحة فارغة.')
                ->columns(2)
                ->schema([
                    self::text('value.credit_title', 'النص الظاهر'),
                    self::text('value.credit_aria_label', 'وصف القسم لقارئ الشاشة'),
                    self::text('value.credit_whatsapp_label', 'اسم زر واتساب'),
                    self::text('value.credit_whatsapp_url', 'رابط واتساب')
                        ->url()
                        ->extraInputAttributes(['dir' => 'ltr']),
                    self::text('value.credit_profile_label', 'اسم زر الملف الشخصي'),
                    self::text('value.credit_profile_url', 'رابط الملف الشخصي')
                        ->url()
                        ->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->columnSpanFull(),
            Placeholder::make('footer_preview')
                ->label('معاينة مبسطة لمكان الظهور')
                ->content(fn (Get $get): HtmlString => self::footerPreview($get))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, TextInput>
     */
    private static function bookingFormFields(): array
    {
        return [
            self::text('value.heading', 'عنوان النموذج')->required(),
            self::text('value.submit_label', 'نص زر الإرسال')->required(),
            self::text('value.name_label', 'اسم حقل العميل')->required(),
            self::text('value.name_placeholder', 'مثال داخل حقل الاسم')->required(),
            self::text('value.occasion_label', 'اسم حقل نوع المناسبة')->required(),
            self::text('value.occasion_placeholder', 'النص قبل اختيار المناسبة')->required(),
            self::text('value.other_label', 'اسم حقل المناسبة الأخرى')->required(),
            self::text('value.other_placeholder', 'مثال داخل حقل المناسبة الأخرى')->required(),
            self::text('value.phone_label', 'اسم حقل رقم التواصل')->required(),
            self::text('value.phone_placeholder', 'مثال داخل حقل الرقم')->required(),
            self::text('value.other_value', 'قيمة تقنية لخيار «أخرى»')
                ->helperText('اتركها كما هي عادة.')
                ->required(),
        ];
    }

    private static function text(
        string $name,
        string $label,
        ?string $helper = null,
    ): TextInput {
        return TextInput::make($name)
            ->label($label)
            ->helperText($helper)
            ->maxLength(500)
            ->live(onBlur: true)
            ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state));
    }

    private static function textarea(
        string $name,
        string $label,
        ?string $helper = null,
    ): Textarea {
        return Textarea::make($name)
            ->label($label)
            ->helperText($helper)
            ->rows(4)
            ->live(onBlur: true)
            ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state));
    }

    private static function footerPreview(Get $get): HtmlString
    {
        $description = e((string) ($get('value.description') ?: 'وصف النشاط'));
        $company = e((string) ($get('value.company_line') ?: 'السطر المختصر'));
        $legal = e((string) ($get('value.legal') ?: 'سطر الحقوق'));
        $credit = e((string) $get('value.credit_title'));

        return new HtmlString(
            '<div style="display:grid;gap:.45rem;padding:1rem 1.1rem;'
            .'border-radius:.85rem;background:#111827;color:#f9fafb">'
            .'<strong>الفوتر</strong>'
            .'<span style="color:#d1d5db">'.$description.'</span>'
            .'<small style="color:#9ca3af">'.$company.'</small>'
            .'<small style="color:#9ca3af">'.$legal.'</small>'
            .($credit !== ''
                ? '<span style="padding-top:.45rem;border-top:1px solid #374151">'
                    .$credit.'</span>'
                : '<span style="color:#6b7280">قسم منفذ الموقع مخفي لأنه فارغ.</span>')
            .'</div>',
        );
    }
}
