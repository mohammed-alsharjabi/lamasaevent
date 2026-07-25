<?php

namespace App\Filament\Resources\Areas\Schemas;

use App\Filament\Forms\ManagedContentFields;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المنطقة')->columns(2)->schema([
                TextInput::make('title')->label('اسم المنطقة')->required(),
                ManagedContentFields::slug(),
                Textarea::make('summary')->label('الملخص')->columnSpanFull(),
                ManagedContentFields::heroMedia(),
                ManagedContentFields::status('areas'),
                ManagedContentFields::publishedAt(),
                Toggle::make('is_active')->label('نشطة')->default(true),
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
