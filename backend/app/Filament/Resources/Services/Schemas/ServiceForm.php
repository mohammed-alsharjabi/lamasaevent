<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Forms\ManagedContentFields;
use App\Models\Media;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\ServiceContentTemplates;
use App\Services\ServiceDefaultsService;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.forms.service-autosave')
                    ->visible(fn (?Service $record): bool => $record instanceof Service)
                    ->columnSpanFull(),
                Section::make('ابدأ بقالب جاهز — اختياري')
                    ->description('اختر قالبًا لإضافة أقسام مرتبة، ثم عدّلها كما تريد. لن يتغير القالب بعد الحفظ إلا بيدك.')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Select::make('service_template')
                            ->label('قالب صفحة الخدمة')
                            ->options(ServiceContentTemplates::options())
                            ->placeholder('ابدأ بصفحة فارغة')
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (filled($state)) {
                                    $set('content_blocks', ServiceContentTemplates::blocks($state));
                                }
                            }),
                    ])
                    ->visible(fn (?Service $record): bool => ! $record)
                    ->columnSpanFull(),
                Section::make('الإضافة السريعة')
                    ->description('أدخل المعلومات الأساسية فقط. الرابط وSEO والتاريخ والترتيب وزر واتساب تُنشأ تلقائيًا داخل Laravel.')
                    ->icon('heroicon-o-bolt')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('اسم الخدمة')
                            ->placeholder('مثال: تنسيق حفلات الزفاف')
                            ->required()
                            ->live(onBlur: true)
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
                        ManagedContentFields::status('services'),
                        Textarea::make('excerpt')
                            ->label('الملخص')
                            ->placeholder('جملة قصيرة واضحة تظهر في بطاقات الخدمة وتُستخدم تلقائيًا لوصف SEO.')
                            ->rows(4)
                            ->live(onBlur: true)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        ...ManagedContentFields::heroMedia(),
                    ])
                    ->columnSpanFull(),
                Section::make('محتوى صفحة الخدمة')
                    ->description('أضف أقسامًا مرئية، رتّبها بالسحب والإفلات، وافتح كل قسم لتعديل محتواه. تُحفظ داخليًا كبيانات JSON منظمة.')
                    ->icon('heroicon-o-rectangle-stack')
                    ->schema([
                        self::contentBuilder(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('إعدادات العرض')
                    ->description('قيم اختيارية. الترتيب وتاريخ النشر يُحددان تلقائيًا عند تركهما فارغين.')
                    ->columns(2)
                    ->schema([
                        ManagedContentFields::featured(),
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
                Section::make('زر الإجراء وواتساب')
                    ->description('يستخدم النظام رقم واتساب واسم الزر من إعدادات الموقع. خصّص القيم فقط لهذه الخدمة عند الحاجة.')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('cta_preview')
                            ->label('المعاينة التلقائية')
                            ->content(fn (Get $get, ?Service $record): HtmlString => self::ctaPreview($get, $record))
                            ->columnSpanFull(),
                        TextInput::make('cta_overrides.label')
                            ->label(fn (Get $get): HtmlString => self::automaticLabel(
                                'تخصيص نص الزر',
                                blank($get('cta_overrides.label')),
                            ))
                            ->placeholder('اتركه فارغًا لاستخدام اسم واتساب العام')
                            ->maxLength(120)
                            ->suffixAction(self::resetAction('resetCtaLabel', 'cta_overrides.label')),
                        TextInput::make('cta_overrides.url')
                            ->label(fn (Get $get): HtmlString => self::automaticLabel(
                                'تخصيص رابط الزر',
                                blank($get('cta_overrides.url')),
                            ))
                            ->url()
                            ->placeholder('اتركه فارغًا لتوليد رابط واتساب')
                            ->maxLength(2048)
                            ->suffixAction(self::resetAction('resetCtaUrl', 'cta_overrides.url')),
                        Select::make('cta_overrides.mode')
                            ->label('إظهار زر واتساب')
                            ->options([
                                'auto' => 'تلقائي — يظهر عند توفر رقم واتساب',
                                'show' => 'إظهار دائمًا',
                                'hide' => 'إخفاء في هذه الخدمة',
                            ])
                            ->default('auto')
                            ->selectablePlaceholder(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('SEO')
                    ->description('القيم التلقائية هي الموصى بها. اضغط «تخصيص» لنسخ القيمة الحالية ثم عدّلها، أو «العودة للتلقائي» لإلغاء التخصيص.')
                    ->columns(2)
                    ->schema([
                        self::seoTextField('title', 'عنوان SEO', fn (Get $get): string => self::generatedSeoTitle($get), 70),
                        self::seoTextField('canonical', 'Canonical URL', fn (Get $get): string => self::generatedCanonical($get), 255)
                            ->url(),
                        self::seoTextarea('description', 'Meta Description', fn (Get $get): string => self::generatedDescription($get), 180)
                            ->columnSpanFull(),
                        TagsInput::make('seo_overrides.keywords')
                            ->label('Meta Keywords — اختياري')
                            ->placeholder('أضف كلمة ثم Enter أو الصق كلمات مفصولة بفواصل')
                            ->helperText('استخدم كلمات مرتبطة فعلًا بالخدمة وتجنب الحشو.')
                            ->splitKeys([',', '،', "\n"])
                            ->rules(['array', 'max:20'])
                            ->nestedRecursiveRules(['string', 'max:60'])
                            ->reorderable()
                            ->columnSpanFull(),
                        Select::make('seo_overrides.robots')
                            ->label(fn (Get $get): HtmlString => self::automaticLabel(
                                'تعليمات محركات البحث',
                                blank($get('seo_overrides.robots')),
                            ))
                            ->options([
                                'index,follow' => 'إظهار الصفحة وتتبع روابطها',
                                'noindex,follow' => 'عدم إظهار الصفحة مؤقتًا',
                                'noindex,nofollow' => 'عدم الإظهار وعدم تتبع الروابط',
                            ])
                            ->placeholder('تلقائي حسب حالة النشر')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('وضع المطور')
                    ->description('غير مطلوب للاستخدام العادي. فعّله فقط لتعديل JSON بدل القيم التي يولدها Laravel.')
                    ->schema([
                        Toggle::make('developer_mode')
                            ->label('تفعيل تعديل JSON المتقدم')
                            ->live()
                            ->dehydrated(false),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                self::developerSection(
                    'Open Graph',
                    'معاينة مشاركة الخدمة في واتساب ومنصات التواصل.',
                    'open_graph',
                    fn (Get $get): HtmlString => self::openGraphPreview($get),
                ),
                self::developerSection(
                    'Twitter Cards / X',
                    'تُولد بطاقة مشاركة متوافقة تلقائيًا من نفس عنوان الخدمة ووصفها وصورتها.',
                    'twitter',
                    fn (Get $get): HtmlString => self::openGraphPreview($get),
                ),
                self::developerSection(
                    'Hreflang',
                    'يولد النظام ar-SA وx-default على رابط الصفحة الصحيح.',
                    'hreflang',
                    fn (Get $get): HtmlString => self::hreflangPreview($get),
                ),
                self::developerSection(
                    'Schema',
                    'يولد Laravel مخطط Service متضمنًا العلامة والصورة وبيانات التواصل والأسئلة.',
                    'json_ld',
                    fn (Get $get): HtmlString => self::schemaPreview($get),
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

    private static function contentBuilder(): Repeater
    {
        return Repeater::make('content_blocks')
            ->label('أقسام الصفحة')
            ->addActionLabel('إضافة قسم')
            ->defaultItems(0)
            ->reorderable()
            ->collapsible()
            ->cloneable()
            ->itemLabel(fn (array $state): string => self::blockLabel((string) ($state['type'] ?? '')))
            ->schema([
                Select::make('type')
                    ->label('نوع القسم')
                    ->options([
                        'intro' => 'مقدمة',
                        'text' => 'نص',
                        'features' => 'قائمة مميزات',
                        'steps' => 'خطوات',
                        'gallery' => 'معرض صور',
                        'cta' => 'دعوة للتواصل CTA',
                        'faq' => 'أسئلة شائعة',
                        'article' => 'قسم مستعاد محمي',
                        'section' => 'قسم مستعاد محمي',
                        'navigation' => 'تنقل مستعاد محمي',
                        'component' => 'مكوّن مستعاد محمي',
                    ])
                    ->required()
                    ->live()
                    ->disableOptionWhen(fn (string $value, Get $get): bool => in_array(
                        $value,
                        ['article', 'section', 'navigation', 'component'],
                        true,
                    ) && $get('type') !== $value),
                TextInput::make('heading')
                    ->label('عنوان القسم — اختياري')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => in_array($get('type'), self::managedBlockTypes(), true)),
                RichEditor::make('lead')
                    ->label('نص المقدمة')
                    ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                    ->visible(fn (Get $get): bool => $get('type') === 'intro')
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->label('النص')
                    ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'link', 'blockquote', 'bulletList', 'orderedList'])
                    ->visible(fn (Get $get): bool => $get('type') === 'text')
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->label(fn (Get $get): string => match ($get('type')) {
                        'features' => 'المميزات',
                        'steps' => 'الخطوات',
                        default => 'الأسئلة',
                    })
                    ->addActionLabel('إضافة عنصر')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['question'] ?? null)
                    ->schema([
                        TextInput::make('title')
                            ->label('العنوان')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => in_array($get('../../type'), ['features', 'steps'], true)),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(2)
                            ->maxLength(1000)
                            ->visible(fn (Get $get): bool => in_array($get('../../type'), ['features', 'steps'], true)),
                        TextInput::make('question')
                            ->label('السؤال')
                            ->maxLength(500)
                            ->visible(fn (Get $get): bool => $get('../../type') === 'faq'),
                        Textarea::make('answer')
                            ->label('الإجابة')
                            ->rows(3)
                            ->maxLength(2000)
                            ->visible(fn (Get $get): bool => $get('../../type') === 'faq'),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['features', 'steps', 'faq'], true))
                    ->columnSpanFull(),
                Select::make('media_ids')
                    ->label('صور المعرض')
                    ->multiple()
                    ->options(fn (): array => Media::query()->orderByDesc('id')->pluck('original_name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->helperText('يمكنك اختيار عدة صور موجودة من مكتبة الوسائط.')
                    ->visible(fn (Get $get): bool => $get('type') === 'gallery')
                    ->columnSpanFull(),
                Textarea::make('text')
                    ->label('النص التشجيعي')
                    ->rows(2)
                    ->maxLength(1000)
                    ->visible(fn (Get $get): bool => $get('type') === 'cta'),
                TextInput::make('label')
                    ->label('نص الزر — اختياري')
                    ->maxLength(120)
                    ->visible(fn (Get $get): bool => $get('type') === 'cta'),
                TextInput::make('url')
                    ->label('رابط الزر — اختياري')
                    ->maxLength(2048)
                    ->visible(fn (Get $get): bool => $get('type') === 'cta'),
                Placeholder::make('legacy_notice')
                    ->label('محتوى مستعاد ومحمي')
                    ->content('هذا القسم مستعاد من الموقع القديم ويُحفظ كما هو لحماية التصميم والروابط. يمكنك نقله أو حذفه، لكن محتواه الخام غير معروض هنا.')
                    ->visible(fn (Get $get): bool => ! in_array($get('type'), self::managedBlockTypes(), true))
                    ->columnSpanFull(),
                Hidden::make('tag'),
                Hidden::make('id'),
                Hidden::make('classes'),
                Hidden::make('headings'),
                Hidden::make('paragraphs'),
                Hidden::make('links'),
                Hidden::make('html'),
            ])
            ->columns(2)
            ->columnSpanFull();
    }

    /** @return list<string> */
    private static function managedBlockTypes(): array
    {
        return ['intro', 'text', 'features', 'steps', 'gallery', 'cta', 'faq'];
    }

    private static function blockLabel(string $type): string
    {
        return match ($type) {
            'intro' => 'مقدمة',
            'text' => 'نص',
            'features' => 'قائمة مميزات',
            'steps' => 'خطوات',
            'gallery' => 'معرض صور',
            'cta' => 'دعوة للتواصل',
            'faq' => 'أسئلة شائعة',
            default => 'قسم مستعاد ومحمي',
        };
    }

    private static function seoTextField(
        string $key,
        string $label,
        callable $generated,
        int $maxLength,
    ): TextInput {
        $path = "seo_overrides.{$key}";

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
        $path = "seo_overrides.{$key}";

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

    private static function resetAction(string $name, string $path): Action
    {
        return Action::make($name)
            ->label('تلقائي')
            ->icon('heroicon-m-arrow-path')
            ->action(fn (Set $set) => $set($path, null))
            ->visible(fn (Get $get): bool => filled($get($path)));
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
                self::jsonEditor("seo_overrides.{$key}", "JSON {$title}")
                    ->helperText('اتركه [] للعودة إلى القيمة التلقائية التي يولدها Laravel.')
                    ->visible(fn (Get $get): bool => (bool) $get('developer_mode')),
            ])
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
        $recordSlug = $get('slug');
        $slug = filled($recordSlug)
            ? (string) $recordSlug
            : (Str::slug((string) $get('title')) ?: 'service');

        return rtrim((string) config('app.production_url'), '/').'/services/'.$slug;
    }

    private static function brandName(): string
    {
        $brand = SiteSetting::query()->where('key', 'brand')->first()?->value;

        return is_array($brand) && filled($brand['name'] ?? null)
            ? (string) $brand['name']
            : (string) config('app.name');
    }

    private static function ctaPreview(Get $get, ?Service $record): HtmlString
    {
        $service = $record ?? new Service;
        $service->title = (string) ($get('title') ?: 'اسم الخدمة');
        $service->setAttribute('cta_overrides', (array) ($get('cta_overrides') ?? []));
        $cta = app(ServiceDefaultsService::class)->cta($service);

        if (! $cta['enabled'] || blank($cta['url'])) {
            return new HtmlString('<span style="color:#6b7280">زر واتساب سيكون مخفيًا.</span>');
        }

        return new HtmlString(
            '<div style="display:flex;align-items:center;gap:.75rem;padding:1rem;'
            .'border:1px solid #d1fae5;border-radius:.85rem;background:#ecfdf5">'
            .'<strong>'.e((string) $cta['label']).'</strong>'
            .'<small style="color:#047857;direction:ltr">'.e((string) $cta['url']).'</small>'
            .'</div>',
        );
    }

    private static function openGraphPreview(Get $get): HtmlString
    {
        $media = Media::query()->find($get('hero_media_id'));
        $image = $media
            ? '<img src="'.e($media->url()).'" alt="" style="width:8rem;height:5rem;object-fit:cover;border-radius:.6rem">'
            : '<span style="width:8rem;height:5rem;display:grid;place-items:center;background:#e5e7eb;border-radius:.6rem">بدون صورة</span>';

        return new HtmlString(
            '<div style="display:flex;gap:1rem;align-items:center;padding:1rem;border:1px solid #e5e7eb;border-radius:.85rem">'
            .$image.'<div><strong>'.e(self::effectiveSeoValue($get, 'title', self::generatedSeoTitle($get))).'</strong>'
            .'<p style="margin:.35rem 0;color:#6b7280">'.e(self::effectiveSeoValue($get, 'description', self::generatedDescription($get))).'</p>'
            .'<small dir="ltr">'.e(self::effectiveSeoValue($get, 'canonical', self::generatedCanonical($get))).'</small></div></div>',
        );
    }

    private static function hreflangPreview(Get $get): HtmlString
    {
        $url = e(self::effectiveSeoValue($get, 'canonical', self::generatedCanonical($get)));

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
        $value = $get("seo_overrides.{$key}");

        return filled($value) ? (string) $value : $default;
    }
}
