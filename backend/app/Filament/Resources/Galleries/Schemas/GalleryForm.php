<?php

namespace App\Filament\Resources\Galleries\Schemas;

use App\Filament\Forms\ManagedContentFields;
use App\Filament\Forms\MediaPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        ...MediaPicker::make('media_id', 'الصورة', 'media', required: true),
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
