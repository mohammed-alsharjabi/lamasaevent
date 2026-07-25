<?php

namespace App\Filament\Resources\ArticleCategories\Schemas;

use App\Filament\Forms\ManagedContentFields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تصنيف المقالات')->columns(2)->schema([
                TextInput::make('name')->label('الاسم')->required(),
                Textarea::make('description')->label('الوصف')->columnSpanFull(),
                ...ManagedContentFields::heroMedia(),
                Toggle::make('is_active')->label('نشط')->default(true),
                ManagedContentFields::order(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
