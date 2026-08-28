<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Filament\Forms\ManagedContentFields;
use App\Models\Article;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Services\ArticleContentSuggestionService;
use App\Services\ArticleSeoAuditService;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.forms.service-autosave')
                    ->visible(fn (?Article $record): bool => $record instanceof Article)
                    ->columnSpanFull(),
                Hidden::make('slug'),
                Hidden::make('content_blocks'),
                Section::make('معلومات المقال')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان المقال')
                            ->placeholder('اكتب عنوانًا واضحًا يصف ما سيجده القارئ')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?Article $record): void {
                                if (blank($state) || $record instanceof Article) {
                                    return;
                                }

                                $suggestions = app(ArticleContentSuggestionService::class)->suggest([
                                    'title' => $state,
                                    'article_category_id' => $get('article_category_id'),
                                    'excerpt' => $get('excerpt'),
                                ]);

                                if (blank($get('excerpt'))) {
                                    $set('excerpt', $suggestions['suggested_excerpt']);
                                }

                                if (self::richContentIsBlank($get('quick_body'))) {
                                    $set('quick_body', $suggestions['suggested_body']);
                                }
                            })
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'اكتب عنوان المقال حتى يمكن حفظه.',
                                'max' => 'عنوان المقال طويل جدًا؛ اختصره إلى 255 حرفًا.',
                            ]),
                        Select::make('article_category_id')
                            ->label('تصنيف المقال')
                            ->relationship('category', 'name')
                            ->placeholder('اختر التصنيف المناسب')
                            ->searchable()
                            ->preload(),
                        Textarea::make('excerpt')
                            ->label('الوصف المختصر')
                            ->placeholder('ملخص قصير يظهر في بطاقة المقال ويُستخدم تلقائيًا في نتيجة البحث.')
                            ->rows(4)
                            ->live(onBlur: true)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        RichEditor::make('quick_body')
                            ->label('محتوى المقال')
                            ->placeholder('اكتب المقال هنا، واستخدم العناوين والقوائم لتسهيل القراءة.')
                            ->toolbarButtons([
                                'h2', 'h3', 'bold', 'italic', 'link', 'blockquote',
                                'bulletList', 'orderedList', 'undo', 'redo',
                            ])
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        ...ManagedContentFields::heroMedia(),
                        Select::make('quick_gallery_media_ids')
                            ->label('صور داخل المقال — اختياري')
                            ->multiple()
                            ->options(fn (): array => Media::query()
                                ->orderByDesc('id')
                                ->pluck('original_name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('يمكنك اختيار عدة صور موجودة؛ سيظهر الاسم ومعاينة مصغرة لكل صورة.')
                            ->columnSpanFull(),
                        Placeholder::make('quick_gallery_preview')
                            ->label('الصور المختارة')
                            ->content(fn (Get $get): HtmlString => self::galleryPreview(
                                (array) ($get('quick_gallery_media_ids') ?? []),
                            ))
                            ->visible(fn (Get $get): bool => filled($get('quick_gallery_media_ids')))
                            ->columnSpanFull(),
                        ManagedContentFields::status('articles')->live(),
                        ManagedContentFields::featured(),
                    ])
                    ->columnSpanFull(),
                Section::make('إعدادات العرض')
                    ->description('قيم اختيارية؛ تاريخ النشر والترتيب يُحددان تلقائيًا عند تركهما فارغين.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('topic')
                            ->label('موضوع داخلي — اختياري')
                            ->placeholder('يُستخدم للتنظيم داخل لوحة التحكم'),
                        TextInput::make('sort_order')
                            ->label(self::automaticLabel('الترتيب', true))
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('تلقائي: بعد آخر مقال في التصنيف'),
                        ManagedContentFields::publishedAt()
                            ->helperText('يُملأ تلقائيًا عند النشر. عدّله فقط للجدولة أو تصحيح التاريخ.'),
                        TagsInput::make('internal_keywords')
                            ->label('وسوم تنظيم داخلية — اختياري')
                            ->helperText('للبحث والتنظيم داخل اللوحة فقط، ولا تُستخدم كـ Meta Keywords.')
                            ->columnSpanFull(),
                        TextInput::make('legacy_path')
                            ->label('المسار الأصلي المحمي')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('لا يوجد — مقال جديد')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('تحسين الظهور في محركات البحث')
                    ->description('القيم التلقائية هي الموصى بها. خصّص أي قيمة فقط عندما تحتاج إلى ذلك.')
                    ->icon('heroicon-o-magnifying-glass')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('google_preview')
                            ->label('معاينة نتيجة Google')
                            ->content(fn (Get $get, ?Article $record): HtmlString => self::googlePreview($get, $record))
                            ->columnSpanFull(),
                        Placeholder::make('seo_audit')
                            ->label('فحص جاهزية المقال')
                            ->content(fn (Get $get, ?Article $record): HtmlString => self::seoAuditPreview($get, $record))
                            ->columnSpanFull(),
                        TextInput::make('target_search_phrase')
                            ->label('عبارة البحث المستهدفة — اختياري')
                            ->placeholder('مثال: أفكار تنسيق حفلات منزلية')
                            ->helperText('تُستخدم لإعطائك اقتراحات عملية، وليست Meta Keywords ولا تضمن ترتيبًا.')
                            ->live(onBlur: true)
                            ->maxLength(160)
                            ->columnSpanFull(),
                        self::seoTextField('seo_title_override', 'عنوان الظهور في Google', fn (Get $get): string => self::generatedSeoTitle($get), 70),
                        self::seoTextField('slug_override', 'تخصيص رابط المقال', fn (Get $get): string => self::generatedSlug($get), 180),
                        self::seoTextField('canonical_override', 'الرابط الأساسي', fn (Get $get): string => self::generatedCanonical($get), 2048)
                            ->url(),
                        Select::make('robots_override')
                            ->label(fn (Get $get): HtmlString => self::automaticLabel(
                                'تعليمات الفهرسة',
                                blank($get('robots_override')),
                            ))
                            ->options([
                                'index,follow' => 'إظهار المقال في البحث وتتبع روابطه',
                                'noindex,follow' => 'عدم إظهار المقال مؤقتًا',
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
                            ->helperText('خصّصه فقط إذا كان عنوان المقال لا يصف الصورة بدقة.')
                            ->columnSpanFull(),
                        Placeholder::make('social_preview')
                            ->label('معاينة مشاركة المقال')
                            ->content(fn (Get $get): HtmlString => self::openGraphPreview($get))
                            ->columnSpanFull(),
                        self::seoTextField('og_title_override', 'عنوان المشاركة', fn (Get $get): string => self::effectiveSeoValue($get, 'seo_title_override', self::generatedSeoTitle($get)), 100),
                        self::seoTextarea('og_description_override', 'وصف المشاركة', fn (Get $get): string => self::effectiveSeoValue($get, 'meta_description_override', self::generatedDescription($get)), 300),
                        TextInput::make('og_image_override')
                            ->label(fn (Get $get): HtmlString => self::automaticLabel(
                                'صورة المشاركة',
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
                    ->description('غير مطلوب للاستخدام العادي. فعّله فقط لتخصيص البيانات التقنية يدويًا.')
                    ->schema([
                        Toggle::make('developer_mode')
                            ->label('تفعيل التعديل التقني المتقدم')
                            ->live()
                            ->dehydrated(false),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                self::developerSection(
                    'Hreflang',
                    'يولد النظام ar-SA وx-default تلقائيًا على رابط المقال.',
                    'hreflang',
                    fn (Get $get): HtmlString => self::hreflangPreview($get),
                ),
                self::developerSection(
                    'Schema',
                    'يولد النظام BlogPosting ومسار التنقل والأسئلة تلقائيًا.',
                    'schema_override',
                    fn (Get $get): HtmlString => self::schemaPreview($get),
                ),
                self::developerSection(
                    'Twitter Cards / X',
                    'تُولد بطاقة المشاركة تلقائيًا من عنوان المقال ووصفه وصورته.',
                    'twitter',
                    fn (Get $get): HtmlString => self::openGraphPreview($get),
                ),
                Section::make('الأسئلة الشائعة')
                    ->description('أضف الأسئلة الحقيقية فقط؛ ستظهر للقارئ وتُضاف إلى بيانات محركات البحث تلقائيًا.')
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
            'title', 'article_category_id', 'excerpt', 'quick_body',
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

    private static function googlePreview(Get $get, ?Article $record): HtmlString
    {
        $audit = app(ArticleSeoAuditService::class)->audit(self::formState($get), $record);
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

    private static function seoAuditPreview(Get $get, ?Article $record): HtmlString
    {
        $audit = app(ArticleSeoAuditService::class)->audit(self::formState($get), $record);
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
        return TextInput::make($key)
            ->label(fn (Get $get): HtmlString => self::automaticLabel($label, blank($get($key))))
            ->placeholder(fn (Get $get): string => $generated($get))
            ->live(onBlur: true)
            ->maxLength($maxLength)
            ->hintActions(self::overrideActions($key, $key, $generated));
    }

    private static function seoTextarea(
        string $key,
        string $label,
        callable $generated,
        int $maxLength,
    ): Textarea {
        return Textarea::make($key)
            ->label(fn (Get $get): HtmlString => self::automaticLabel($label, blank($get($key))))
            ->placeholder(fn (Get $get): string => $generated($get))
            ->rows(3)
            ->live(onBlur: true)
            ->maxLength($maxLength)
            ->hintActions(self::overrideActions($key, $key, $generated));
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
                    ->helperText('اتركه [] للعودة إلى القيمة التلقائية.')
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
        $title = trim((string) $get('title')) ?: 'عنوان المقال';
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

        return rtrim((string) config('app.production_url'), '/').'/blog/'.$slug;
    }

    private static function generatedSlug(Get $get): string
    {
        return filled($get('slug'))
            ? (string) $get('slug')
            : (Str::slug((string) $get('title')) ?: 'article');
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
            .'<strong>نوع بيانات المقال: BlogPosting</strong><p style="margin:.35rem 0 0;color:#6b7280">'
            .'العنوان: '.e((string) ($get('title') ?: 'عنوان المقال')).' · الناشر: '
            .e(self::brandName()).' · اللغة: ar-SA</p></div>',
        );
    }

    private static function effectiveSeoValue(Get $get, string $key, string $default): string
    {
        return filled($get($key)) ? (string) $get($key) : $default;
    }

    private static function richContentIsBlank(mixed $value): bool
    {
        $html = is_array($value)
            ? RichContentRenderer::make($value)->toHtml()
            : (string) $value;

        return trim(strip_tags(html_entity_decode($html))) === '';
    }
}
