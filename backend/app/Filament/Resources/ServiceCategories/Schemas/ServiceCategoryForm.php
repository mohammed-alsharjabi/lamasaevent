<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use App\Filament\Forms\ManagedContentFields;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تصنيف الخدمات')->columns(2)->schema([
                TextInput::make('title')->label('الاسم')->required(),
                ManagedContentFields::slug(),
                Textarea::make('summary')->label('الملخص')->columnSpanFull(),
                ManagedContentFields::heroMedia(),
                ManagedContentFields::status('service-categories'),
                ManagedContentFields::publishedAt(),
                Toggle::make('is_active')->label('نشط')->default(true),
                ManagedContentFields::order(),
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
