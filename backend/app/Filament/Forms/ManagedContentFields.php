<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class ManagedContentFields
{
    public static function status(string $permissionGroup): Select
    {
        return Select::make('status')
            ->label('حالة النشر')
            ->options(function () use ($permissionGroup): array {
                $options = ['draft' => 'مسودة'];

                if (auth()->user()?->hasPermission("{$permissionGroup}.publish")) {
                    $options['published'] = 'منشور';
                }

                return $options;
            })
            ->default('draft')
            ->helperText('المسودة لا تظهر في الموقع. اختر «منشور» لإرسال التحديث إلى الواجهة العامة.')
            ->required();
    }

    public static function publishedAt(): DateTimePicker
    {
        return DateTimePicker::make('published_at')
            ->label('تاريخ النشر')
            ->seconds(false)
            ->timezone(config('app.timezone'));
    }

    /**
     * @return array{Select, Placeholder}
     */
    public static function heroMedia(): array
    {
        return MediaPicker::make();
    }

    public static function contentBlocks(): CodeEditor
    {
        return self::jsonEditor('content_blocks', 'كتل المحتوى المنظمة')
            ->helperText('بيانات الاستعادة محفوظة بصيغة JSON دون فقد HTML أو الروابط الداخلية.')
            ->columnSpanFull();
    }

    public static function faqs(): Repeater
    {
        return Repeater::make('faqs')
            ->label('الأسئلة الشائعة')
            ->relationship()
            ->orderColumn('sort_order')
            ->defaultItems(0)
            ->schema([
                TextInput::make('question')->label('السؤال')->required(),
                Textarea::make('answer')->label('الإجابة')->required()->columnSpanFull(),
                Toggle::make('is_active')->label('ظاهر')->default(true),
            ])
            ->collapsed()
            ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
            ->columnSpanFull();
    }

    public static function seo(): Section
    {
        return Section::make('تحسين محركات البحث وبيانات المشاركة')
            ->description('كل الحقول اختيارية. عند ترك العنوان أو الوصف أو Canonical فارغًا، ينشئ النظام قيمة مناسبة تلقائيًا من الصفحة.')
            ->relationship('seoMeta')
            ->schema([
                TextInput::make('title')
                    ->label('عنوان SEO (اختياري)')
                    ->placeholder('يُستخدم عنوان المحتوى تلقائيًا')
                    ->maxLength(70),
                TextInput::make('canonical')
                    ->label('Canonical URL (اختياري)')
                    ->url()
                    ->placeholder('يُولد رابط الصفحة الصحيح تلقائيًا')
                    ->helperText('اتركه فارغًا للاستخدام الآمن الموصى به: Canonical ذاتي على رابط الصفحة المنشور.')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Meta Description (اختياري)')
                    ->rows(3)
                    ->placeholder('يُستخدم الملخص أو الوصف المختصر تلقائيًا')
                    ->maxLength(180)
                    ->columnSpanFull(),
                TagsInput::make('keywords')
                    ->label('Meta Keywords (اختياري)')
                    ->placeholder('أضف كلمة ثم Enter، أو الصق كلمات مفصولة بفواصل')
                    ->helperText('يمكن لصق الكلمات دفعة واحدة بفاصلة عربية (،) أو إنجليزية (,). استخدم كلمات مرتبطة فعلًا بالمحتوى وتجنب الحشو.')
                    ->splitKeys([',', '،', "\n"])
                    ->rules(['array', 'max:20'])
                    ->nestedRecursiveRules(['string', 'max:60'])
                    ->reorderable()
                    ->columnSpanFull(),
                TextInput::make('robots')
                    ->label('Robots (اختياري)')
                    ->placeholder('index,follow'),
                self::jsonEditor('open_graph', 'Open Graph'),
                self::jsonEditor('twitter', 'Twitter Cards'),
                self::jsonEditor('hreflang', 'Hreflang'),
                self::jsonEditor('json_ld', 'Schema JSON-LD')->columnSpanFull(),
            ])
            ->collapsed()
            ->columnSpanFull();
    }

    public static function featured(): Toggle
    {
        return Toggle::make('is_featured')->label('مميز')->default(false);
    }

    public static function order(): TextInput
    {
        return TextInput::make('sort_order')
            ->label('الترتيب')
            ->numeric()
            ->default(0)
            ->required();
    }

    private static function jsonEditor(string $name, string $label): CodeEditor
    {
        return CodeEditor::make($name)
            ->label($label)
            ->language(Language::Json)
            ->formatStateUsing(
                fn (mixed $state): string => json_encode(
                    $state ?? [],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ) ?: '[]',
            )
            ->dehydrateStateUsing(
                fn (?string $state): array => json_decode($state ?: '[]', true) ?: [],
            )
            ->rules(['json']);
    }
}
