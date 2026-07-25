<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Filament\Forms\MediaPicker;
use App\Models\Media;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
                Placeholder::make('current_preview')
                    ->label('معاينة الصورة')
                    ->content(fn (?Media $record) => MediaPicker::preview($record))
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->columnSpanFull(),
                FileUpload::make('upload')
                    ->label('رفع صورة')
                    ->image()
                    ->storeFiles(false)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize((int) ceil(config('media.max_bytes') / 1024))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->columnSpanFull(),
                TextInput::make('title')->label('العنوان')->maxLength(255),
                TextInput::make('alt')->label('النص البديل')->required()->maxLength(255),
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
