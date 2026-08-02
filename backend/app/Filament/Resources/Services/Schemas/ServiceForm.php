<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Forms\ManagedContentFields;
use App\Models\Media;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\ServiceContentSuggestionService;
use App\Services\ServiceSeoAuditService;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.forms.service-autosave')
                    ->visible(fn (?Service $record): bool => $record instanceof Service)
                    ->columnSpanFull(),
                Hidden::make('slug'),
                Hidden::make('content_blocks'),
                Hidden::make('cta_overrides'),
                Section::make('معلومات الخدمة')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('اسم الخدمة')
                            ->placeholder('مثال: تنسيق حفلات الزفاف')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?Service $record): void {
                                if (blank($state) || $record instanceof Service) {
                                    return;
                                }

                                $suggestions = app(ServiceContentSuggestionService::class)->suggest([
                                    'title' => $state,
                                    'service_category_id' => $get('service_category_id'),
                                    'excerpt' => $get('excerpt'),
                                ]);

                                if (blank($get('excerpt'))) {
                                    $set('excerpt', $suggestions['suggested_excerpt']);
                                }

                                if (self::richContentIsBlank($get('quick_details'))) {
                                    $set('quick_details', $suggestions['suggested_intro']);
                                }
                            })
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'اكتب اسم الخدمة حتى يمكن حفظها.',
                                'max' => 'اسم الخدمة طويل جدًا؛ اختصره إلى 255 حرفًا.',
                            ]),
                        Select::make('service_category_id')
                            ->label('تصنيف الخدمة')
                            ->relationship('category', 'title')
                            ->placeholder('اختر التصنيف المناسب')
                            ->searchable()
                            ->preload(),
                        Select::make('parent_id')
                            ->label('الخدمة الرئيسية — اختياري')
                            ->relationship(
                                'parent',
                                'title',
                                modifyQueryUsing: fn ($query) => $query->whereNull('parent_id'),
                                ignoreRecord: true,
                            )
                            ->placeholder('بدون — هذه خدمة رئيسية')
                            ->helperText('استخدمه فقط إذا كانت هذه الخدمة فرعية من خدمة أخرى.')
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?Service $record): bool => $record?->children()->exists() ?? false)
                            ->dehydrated(),
                        Textarea::make('excerpt')
                            ->label('الوصف المختصر')
                            ->placeholder('جملة قصيرة واضحة تظهر في بطاقات الخدمة وتُستخدم تلقائيًا لوصف SEO.')
                            ->rows(4)
                            ->live(onBlur: true)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        RichEditor::make('quick_details')
                            ->label('تفاصيل الخدمة')
                            ->placeholder('اشرح ما الذي يحصل عليه العميل، وكيف تنفذ الخدمة، وما الذي يميزها.')
                            ->toolbarButtons([
                                'h2', 'h3', 'bold', 'italic', 'link', 'blockquote',
                                'bulletList', 'orderedList', 'undo', 'redo',
                            ])
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        ...ManagedContentFields::heroMedia(),
                        Select::make('quick_gallery_media_ids')
                            ->label('معرض الصور — اختياري')
                            ->multiple()
                            ->options(fn (): array => Media::query()
                                ->orderByDesc('id')
                                ->pluck('original_name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('يمكنك اختيار عدة صور موجودة؛ يظهر اسم كل صورة ومعاينة مصغرة أدناه.')
                            ->columnSpanFull(),
                        Placeholder::make('quick_gallery_preview')
                            ->label('الصور المختارة')
                            ->content(fn (Get $get): HtmlString => self::galleryPreview(
                                (array) ($get('quick_gallery_media_ids') ?? []),
                            ))
                            ->visible(fn (Get $get): bool => filled($get('quick_gallery_media_ids')))
                            ->columnSpanFull(),
                        ManagedContentFields::status('services')->live(),
                        ManagedContentFields::featured(),
                    ])
                    ->columnSpanFull(),
                Section::make('إعدادات العرض')
                    ->description('قيم اختيارية. الترتيب وتاريخ النشر يُحددان تلقائيًا عند تركهما فارغين.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sort_order')
                            ->label(self::automaticLabel('الترتيب', true))
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('تلقائي: بعد آخر خدمة في التصنيف')
                            ->helperText('خصّص الرقم فقط إذا أردت ترتيبًا يدويًا.'),
                        ManagedContentFields::publishedAt()
                            ->helperText('يُملأ الآن تلقائيًا عند اختيار «منشور». عدّله فقط لجدولة أو تصحيح التاريخ.'),
                        TextInput::make('legacy_path')
                            ->label('المسار الأصلي المحمي')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('لا يوجد — خدمة جديدة')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('تحسين الظهور في محركات البحث')
                    ->description('يعرض النظام النتيجة الفعلية التي سيحصل عليها الموقع. لا تحتاج لتعديل شيء إلا إذا ظهر تنبيه واضح.')
                    ->icon('heroicon-o-magnifying-glass')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('google_preview')
                            ->label('معاينة نتيجة Google')
                            ->content(fn (Get $get, ?Service $record): HtmlString => self::googlePreview($get, $record))
                            ->columnSpanFull(),
                        Placeholder::make('seo_audit')
                            ->label('فحص جاهزية الصفحة')
                            ->content(fn (Get $get, ?Service $record): HtmlString => self::seoAuditPreview($get, $record))
                            ->columnSpanFull(),
                        TextInput::make('target_search_phrase')
                            ->label('عبارة البحث المستهدفة — اختياري')
                            ->placeholder('مثال: تنظيم حفلات زفاف في الرياض')
                            ->helperText('تُستخدم فقط لإعطائك اقتراحات تحريرية، وليست Meta Keywords ولا تضمن ترتيبًا في Google.')
                            ->live(onBlur: true)
                            ->maxLength(160)
                            ->columnSpanFull(),
                        self::seoTextField('seo_title_override', 'عنوان الظهور في Google', fn (Get $get): string => self::generatedSeoTitle($get), 70),
                        self::seoTextField('slug_override', 'تخصيص الرابط المختصر', fn (Get $get): string => self::generatedSlug($get), 180),
                        self::seoTextField('canonical_override', 'الرابط الأساسي Canonical', fn (Get $get): string => self::generatedCanonical($get), 2048)
                            ->url(),
                        Select::make('robots_override')
                            ->label(fn (Get $get): HtmlString => self::automaticLabel(
                                'تعليمات الفهرسة',
                                blank($get('robots_override')),
                            ))
                            ->options([
                                'index,follow' => 'إظهار الصفحة في البحث وتتبع روابطها',
                                'noindex,follow' => 'عدم إظهار الصفحة مؤقتًا',
                                'noindex,nofollow' => 'عدم الإظهار وعدم تتبع الروابط',
                            ])
                            ->placeholder('تلقائي حسب حالة النشر')
                            ->hintActions(self::overrideActions(
                                'robots',
                                'robots_override',
                                fn (Get $get): string => $get('status') === 'published'
                                    ? 'index,follow'
                                    : 'noindex,nofollow',
                            )),
                        self::seoTextarea('meta_description_override', 'وصف نتيجة البحث', fn (Get $get): string => self::generatedDescription($get), 180)
                            ->columnSpanFull(),
                        self::seoTextField('hero_alt_override', 'النص البديل للصورة', fn (Get $get): string => trim((string) $get('title')), 255)
                            ->helperText('صف الصورة باختصار إذا كانت القيمة المقترحة لا تعبّر عنها.')
                            ->columnSpanFull(),
                        Placeholder::make('social_preview')
                            ->label('معاينة المشاركة')
                            ->content(fn (Get $get): HtmlString => self::openGraphPreview($get))
                            ->columnSpanFull(),
                        self::seoTextField('og_title_override', 'عنوان المشاركة', fn (Get $get): string => self::effectiveSeoValue($get, 'seo_title_override', self::generatedSeoTitle($get)), 100),
                        self::seoTextarea('og_description_override', 'وصف المشاركة', fn (Get $get): string => self::effectiveSeoValue($get, 'meta_description_override', self::generatedDescription($get)), 300),
                        TextInput::make('og_image_override')
                            ->label(fn (Get $get): HtmlString => self::automaticLabel(
                                'رابط صورة المشاركة',
                                blank($get('og_image_override')),
                            ))
                            ->url()
                            ->maxLength(2048)
                            ->placeholder('تلقائي من الصورة البارزة')
                            ->hintActions(self::overrideActions(
                                'og_image',
                                'og_image_override',
                                fn (Get $get): string => Media::query()->find($get('hero_media_id'))?->url() ?? '',
                            ))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('وضع المطور')
                    ->description('غير مطلوب للاستخدام العادي. فعّله فقط إذا كنت تحتاج إلى تخصيص تقني متقدم.')
                    ->schema([
                        Toggle::make('developer_mode')
                            ->label('تفعيل تعديل JSON المتقدم')
                            ->live()
                            ->dehydrated(false),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                self::developerSection(
                    'Hreflang',
                    'يولد النظام ar-SA وx-default على رابط الصفحة الصحيح.',
                    'hreflang',
                    fn (Get $get): HtmlString => self::hreflangPreview($get),
                ),
                self::developerSection(
                    'Schema',
                    'يولد النظام مخططي Service وBreadcrumbList متضمنين العلامة والصورة وبيانات التواصل والأسئلة.',
                    'schema_override',
                    fn (Get $get): HtmlString => self::schemaPreview($get),
                ),
                self::developerSection(
                    'Twitter Cards / X',
                    'تُولد البطاقة تلقائيًا من بيانات المشاركة، ويمكن تخصيص JSON هنا فقط عند الضرورة.',
                    'twitter',
                    fn (Get $get): HtmlString => self::openGraphPreview($get),
                ),
                Section::make('الأسئلة الشائعة')
                    ->description('أسئلة منظمة تظهر للمستخدم ويستفيد منها Schema تلقائيًا. أضف فقط الأسئلة الحقيقية.')
                    ->schema([
                        ManagedContentFields::faqs(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    /** @return array<string, mixed> */
    private static function formState(Get $get): array
    {
        $keys = [
            'title', 'service_category_id', 'parent_id', 'excerpt', 'quick_details',
            'hero_media_id', 'status', 'slug', 'seo_title_override',
            'meta_description_override', 'slug_override', 'canonical_override',
            'robots_override', 'og_title_override', 'og_description_override',
            'og_image_override', 'schema_override', 'target_search_phrase',
            'hero_alt_override', 'seo_overrides',
        ];

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $get($key)])->all();
    }

    /** @param list<int|string> $ids */
    private static function galleryPreview(array $ids): HtmlString
    {
        $mediaItems = Media::query()
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (Media $media): string => (string) $media->getKey());
        $cards = collect($ids)->map(function (mixed $id) use ($mediaItems): string {
            $media = $mediaItems->get((string) $id);

            if (! $media instanceof Media) {
                return '';
            }

            return '<div style="display:flex;align-items:center;gap:.55rem;padding:.5rem;'
                .'border:1px solid #e5e7eb;border-radius:.65rem">'
                .'<img src="'.e($media->url()).'" alt="'.e($media->alt ?: $media->original_name).'" '
                .'style="width:4rem;height:3rem;object-fit:cover;border-radius:.4rem">'
                .'<span style="font-size:.82rem;word-break:break-word">'.e($media->original_name).'</span></div>';
        })->filter()->implode('');

        return new HtmlString(
            '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));gap:.6rem">'
            .$cards.'</div>',
        );
    }

    private static function googlePreview(Get $get, ?Service $record): HtmlString
    {
        $audit = app(ServiceSeoAuditService::class)->audit(self::formState($get), $record);
        $values = $audit['values'];

        return new HtmlString(
            '<div dir="rtl" style="max-width:42rem;padding:1rem;border:1px solid #e5e7eb;'
            .'border-radius:.85rem;background:#fff">'
            .'<div dir="ltr" style="color:#202124;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
            .e((string) $values['canonical']).'</div>'
            .'<div style="color:#1a0dab;font-size:1.25rem;line-height:1.5;margin:.2rem 0">'
            .e((string) $values['title']).'</div>'
            .'<p style="color:#4d5156;margin:0;line-height:1.65">'.e((string) $values['description']).'</p>'
            .'</div>',
        );
    }

    private static function seoAuditPreview(Get $get, ?Service $record): HtmlString
    {
        $audit = app(ServiceSeoAuditService::class)->audit(self::formState($get), $record);
        $ready = (bool) $audit['ready'];
        $items = collect($audit['checks'])->map(function (array $check): string {
            $suggestion = $check['severity'] === 'suggestion';
            $ok = (bool) $check['ok'];
            $color = $suggestion ? '#92400e' : ($ok ? '#047857' : '#b91c1c');
            $icon = $suggestion ? '💡' : ($ok ? '✓' : '!');

            return '<li style="display:flex;gap:.55rem;align-items:flex-start;color:'.$color.'">'
                .'<b aria-hidden="true">'.$icon.'</b><span><strong>'.e((string) $check['label'])
                .':</strong> '.e((string) $check['message']).'</span></li>';
        })->implode('');

        return new HtmlString(
            '<div style="padding:1rem;border-radius:.85rem;border:1px solid '
            .($ready ? '#a7f3d0' : '#fecaca').';background:'.($ready ? '#ecfdf5' : '#fef2f2').'">'
            .'<strong style="color:'.($ready ? '#047857' : '#b91c1c').'">'.e($audit['status']).'</strong>'
            .'<ul style="display:grid;gap:.55rem;margin:.85rem 0 0;padding:0;list-style:none">'.$items.'</ul>'
            .'</div>',
        );
    }

    private static function seoTextField(
        string $key,
        string $label,
        callable $generated,
        int $maxLength,
    ): TextInput {
        $path = $key;

        return TextInput::make($path)
            ->label(fn (Get $get): HtmlString => self::automaticLabel($label, blank($get($path))))
            ->placeholder(fn (Get $get): string => $generated($get))
            ->live(onBlur: true)
            ->maxLength($maxLength)
            ->hintActions(self::overrideActions($key, $path, $generated));
    }

    private static function seoTextarea(
        string $key,
        string $label,
        callable $generated,
        int $maxLength,
    ): Textarea {
        $path = $key;

        return Textarea::make($path)
            ->label(fn (Get $get): HtmlString => self::automaticLabel($label, blank($get($path))))
            ->placeholder(fn (Get $get): string => $generated($get))
            ->rows(3)
            ->live(onBlur: true)
            ->maxLength($maxLength)
            ->hintActions(self::overrideActions($key, $path, $generated));
    }

    /** @return array<Action> */
    private static function overrideActions(string $key, string $path, callable $generated): array
    {
        return [
            Action::make("customize_{$key}")
                ->label('تخصيص')
                ->icon('heroicon-m-pencil-square')
                ->action(fn (Get $get, Set $set) => $set($path, $generated($get)))
                ->visible(fn (Get $get): bool => blank($get($path))),
            Action::make("reset_{$key}")
                ->label('العودة للتلقائي')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn (Set $set) => $set($path, null))
                ->visible(fn (Get $get): bool => filled($get($path))),
        ];
    }

    private static function developerSection(
        string $title,
        string $description,
        string $key,
        callable $preview,
    ): Section {
        return Section::make($title)
            ->description($description)
            ->schema([
                Placeholder::make("{$key}_preview")
                    ->label('معاينة مفهومة')
                    ->content(fn (Get $get): HtmlString => $preview($get))
                    ->visible(fn (Get $get): bool => ! $get('developer_mode')),
                self::jsonEditor(
                    $key === 'schema_override' ? $key : "seo_overrides.{$key}",
                    "JSON {$title}",
                )
                    ->helperText('اتركه [] للعودة إلى القيمة التلقائية التي يولدها النظام.')
                    ->visible(fn (Get $get): bool => (bool) $get('developer_mode')),
            ])
            ->visible(fn (Get $get): bool => (bool) $get('developer_mode'))
            ->collapsed()
            ->columnSpanFull();
    }

    private static function jsonEditor(string $name, string $label): CodeEditor
    {
        return CodeEditor::make($name)
            ->label($label)
            ->language(Language::Json)
            ->formatStateUsing(fn (mixed $state): string => json_encode(
                $state ?? [],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) ?: '[]')
            ->dehydrateStateUsing(fn (?string $state): array => json_decode($state ?: '[]', true) ?: [])
            ->rules(['json'])
            ->columnSpanFull();
    }

    private static function automaticLabel(string $label, bool $automatic): HtmlString
    {
        $badge = $automatic ? 'تلقائي' : 'مخصص';
        $background = $automatic ? '#ecfdf5' : '#eff6ff';
        $color = $automatic ? '#047857' : '#1d4ed8';

        return new HtmlString(
            e($label).' <span style="display:inline-flex;padding:.12rem .45rem;'
            .'border-radius:999px;background:'.$background.';color:'.$color.';'
            .'font-size:.72rem;font-weight:700">'.$badge.'</span>',
        );
    }

    private static function generatedSeoTitle(Get $get): string
    {
        $title = trim((string) $get('title')) ?: 'اسم الخدمة';
        $brand = self::brandName();

        if ($brand !== '' && ! str_contains(mb_strtolower($title), mb_strtolower($brand))) {
            $title .= ' | '.$brand;
        }

        return Str::limit($title, 70, '');
    }

    private static function generatedDescription(Get $get): string
    {
        $description = trim(strip_tags((string) $get('excerpt')));

        return Str::limit($description ?: self::generatedSeoTitle($get), 180, '');
    }

    private static function generatedCanonical(Get $get): string
    {
        $slug = filled($get('slug_override'))
            ? Str::slug((string) $get('slug_override'))
            : self::generatedSlug($get);

        return rtrim((string) config('app.production_url'), '/').'/services/'.$slug;
    }

    private static function generatedSlug(Get $get): string
    {
        $recordSlug = $get('slug');

        return filled($recordSlug)
            ? (string) $recordSlug
            : (Str::slug((string) $get('title')) ?: 'service');
    }

    private static function brandName(): string
    {
        $brand = SiteSetting::query()->where('key', 'brand')->first()?->value;

        return is_array($brand) && filled($brand['name'] ?? null)
            ? (string) $brand['name']
            : (string) config('app.name');
    }

    private static function openGraphPreview(Get $get): HtmlString
    {
        $media = Media::query()->find($get('hero_media_id'));
        $image = $media
            ? '<img src="'.e($media->url()).'" alt="" style="width:8rem;height:5rem;object-fit:cover;border-radius:.6rem">'
            : '<span style="width:8rem;height:5rem;display:grid;place-items:center;background:#e5e7eb;border-radius:.6rem">بدون صورة</span>';

        return new HtmlString(
            '<div style="display:flex;gap:1rem;align-items:center;padding:1rem;border:1px solid #e5e7eb;border-radius:.85rem">'
            .$image.'<div><strong>'.e(self::effectiveSeoValue($get, 'og_title_override', self::effectiveSeoValue($get, 'seo_title_override', self::generatedSeoTitle($get)))).'</strong>'
            .'<p style="margin:.35rem 0;color:#6b7280">'.e(self::effectiveSeoValue($get, 'og_description_override', self::effectiveSeoValue($get, 'meta_description_override', self::generatedDescription($get)))).'</p>'
            .'<small dir="ltr">'.e(self::effectiveSeoValue($get, 'canonical_override', self::generatedCanonical($get))).'</small></div></div>',
        );
    }

    private static function hreflangPreview(Get $get): HtmlString
    {
        $url = e(self::effectiveSeoValue($get, 'canonical_override', self::generatedCanonical($get)));

        return new HtmlString(
            '<div style="display:grid;gap:.45rem"><span><b>العربية (السعودية):</b> '
            .$url.'</span><span><b>الافتراضي:</b> '.$url.'</span></div>',
        );
    }

    private static function schemaPreview(Get $get): HtmlString
    {
        return new HtmlString(
            '<div style="padding:1rem;border-radius:.85rem;background:#f9fafb;border:1px solid #e5e7eb">'
            .'<strong>نوع المخطط: Service</strong><p style="margin:.35rem 0 0;color:#6b7280">'
            .'الاسم: '.e((string) ($get('title') ?: 'اسم الخدمة')).' · المزود: '
            .e(self::brandName()).' · اللغة: ar-SA</p></div>',
        );
    }

    private static function effectiveSeoValue(Get $get, string $key, string $default): string
    {
        $value = $get($key);

        return filled($value) ? (string) $value : $default;
    }

    private static function richContentIsBlank(mixed $value): bool
    {
        $html = is_array($value)
            ? RichContentRenderer::make($value)->toHtml()
            : (string) $value;

        return trim(strip_tags(html_entity_decode($html))) === '';
    }
}
