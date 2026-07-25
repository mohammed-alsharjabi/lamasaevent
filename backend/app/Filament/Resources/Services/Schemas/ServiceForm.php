<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Forms\ManagedContentFields;
use App\Models\Service;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الخدمة')->columns(2)->schema([
                TextInput::make('title')->label('اسم الخدمة')->required()->maxLength(255),
                Select::make('service_category_id')
                    ->label('تصنيف الخدمة')
                    ->relationship('category', 'title')
                    ->searchable()
                    ->preload(),
                Select::make('parent_id')
                    ->label('الخدمة الرئيسية')
                    ->relationship(
                        'parent',
                        'title',
                        modifyQueryUsing: fn ($query) => $query->whereNull('parent_id'),
                        ignoreRecord: true,
                    )
                    ->placeholder('بدون — هذه خدمة رئيسية')
                    ->helperText('اختر خدمة رئيسية فقط عندما تكون هذه الخدمة فرعية.')
                    ->searchable()
                    ->preload()
                    ->disabled(
                        fn (?Service $record): bool => $record?->children()->exists() ?? false,
                    )
                    ->dehydrated(),
                ...ManagedContentFields::heroMedia(),
                Textarea::make('excerpt')->label('الملخص')->rows(3)->columnSpanFull(),
                ManagedContentFields::status('services'),
                ManagedContentFields::publishedAt(),
                ManagedContentFields::featured(),
                ManagedContentFields::order(),
                TextInput::make('cta_label')->label('نص زر الإجراء'),
                TextInput::make('cta_url')->label('رابط زر الإجراء')->maxLength(2048),
                Toggle::make('whatsapp_enabled')->label('إظهار واتساب')->default(true),
                TextInput::make('legacy_path')
                    ->label('المسار الأصلي')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpanFull(),
                ManagedContentFields::contentBlocks(),
                ManagedContentFields::faqs(),
            ])->columnSpanFull(),
            ManagedContentFields::seo(),
        ])->columns(1);
    }
}
