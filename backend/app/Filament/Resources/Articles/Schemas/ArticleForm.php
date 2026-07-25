<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Filament\Forms\ManagedContentFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المقال')->columns(2)->schema([
                TextInput::make('title')->label('العنوان')->required()->maxLength(255),
                Select::make('article_category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                ManagedContentFields::slug(),
                TextInput::make('topic')->label('الموضوع'),
                Textarea::make('excerpt')->label('المقتطف')->rows(3)->columnSpanFull(),
                ManagedContentFields::heroMedia(),
                ManagedContentFields::status('articles'),
                ManagedContentFields::publishedAt(),
                ManagedContentFields::featured(),
                ManagedContentFields::order(),
                TagsInput::make('internal_keywords')->label('كلمات داخلية')->columnSpanFull(),
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
