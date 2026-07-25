<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Forms\ManagedContentFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الصفحة')->columns(2)->schema([
                TextInput::make('title')->label('العنوان')->required(),
                Select::make('type')->label('النوع')->options([
                    'page' => 'صفحة',
                    'home' => 'الرئيسية',
                    'listing' => 'قائمة',
                    'contact' => 'تواصل',
                ])->required(),
                TextInput::make('path')
                    ->label('المسار')
                    ->startsWith('/')
                    ->unique(ignoreRecord: true)
                    ->disabled(fn ($record): bool => (bool) $record?->routeRecord?->slug_locked)
                    ->dehydrated()
                    ->required(),
                ManagedContentFields::heroMedia(),
                Textarea::make('summary')->label('الملخص')->columnSpanFull(),
                ManagedContentFields::status('pages'),
                ManagedContentFields::publishedAt(),
                ManagedContentFields::featured(),
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
