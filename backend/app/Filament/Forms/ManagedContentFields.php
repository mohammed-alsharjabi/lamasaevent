<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class ManagedContentFields
{
    public static function slug(): TextInput
    {
        return TextInput::make('slug')
            ->label('الرابط المختصر (Slug)')
            ->helperText('يُقفل بعد النشر. تغييره لاحقًا متاح فقط عبر إجراء تحويل 301.')
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->unique(ignoreRecord: true)
            ->disabled(fn (?Model $record): bool => (bool) $record?->isPublished())
            ->dehydrated()
            ->required()
            ->maxLength(180);
    }

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
            ->required();
    }

    public static function publishedAt(): DateTimePicker
    {
        return DateTimePicker::make('published_at')
            ->label('تاريخ النشر')
            ->seconds(false)
            ->timezone(config('app.timezone'));
    }

    public static function heroMedia(): Select
    {
        return Select::make('hero_media_id')
            ->label('الصورة البارزة')
            ->relationship('heroMedia', 'original_name')
            ->searchable()
            ->preload();
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
            ->description('العنوان والوصف والرابط القانوني وOpen Graph وSchema JSON-LD.')
            ->relationship('seoMeta')
            ->schema([
                TextInput::make('title')
                    ->label('عنوان SEO')
                    ->required()
                    ->maxLength(70),
                TextInput::make('canonical')
                    ->label('Canonical URL')
                    ->url()
                    ->required()
                    ->maxLength(2048),
                Textarea::make('description')
                    ->label('Meta Description')
                    ->rows(3)
                    ->required()
                    ->maxLength(180)
                    ->columnSpanFull(),
                TextInput::make('robots')
                    ->label('Robots')
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
