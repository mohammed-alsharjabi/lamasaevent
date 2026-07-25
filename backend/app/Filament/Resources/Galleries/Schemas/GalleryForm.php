<?php

namespace App\Filament\Resources\Galleries\Schemas;

use App\Filament\Forms\ManagedContentFields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GalleryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المعرض')->columns(2)->schema([
                TextInput::make('title')->label('العنوان')->required(),
                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->required(),
                Textarea::make('description')->label('الوصف')->columnSpanFull(),
                ManagedContentFields::status('galleries'),
                ManagedContentFields::publishedAt(),
                ManagedContentFields::featured(),
                ManagedContentFields::order(),
                Repeater::make('items')
                    ->label('صور المعرض')
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->schema([
                        Select::make('media_id')
                            ->label('الصورة')
                            ->relationship('media', 'original_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')->label('العنوان'),
                        TextInput::make('alt')->label('النص البديل'),
                        Textarea::make('caption')->label('الوصف'),
                        Toggle::make('is_active')->label('ظاهرة')->default(true),
                    ])
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
