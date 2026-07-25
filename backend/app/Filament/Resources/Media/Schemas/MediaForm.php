<?php

namespace App\Filament\Resources\Media\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الصورة')->columns(2)->schema([
                FileUpload::make('upload')
                    ->label('رفع صورة')
                    ->image()
                    ->storeFiles(false)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(8192)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->columnSpanFull(),
                TextInput::make('title')->label('العنوان'),
                TextInput::make('alt')->label('النص البديل')->required(),
                Textarea::make('caption')->label('الوصف')->columnSpanFull(),
                TextInput::make('original_name')->label('اسم الملف')->disabled(),
                TextInput::make('mime_type')->label('نوع الملف')->disabled(),
                TextInput::make('width')->label('العرض')->disabled(),
                TextInput::make('height')->label('الارتفاع')->disabled(),
                TextInput::make('webp_path')->label('نسخة WebP')->disabled()->columnSpanFull(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
