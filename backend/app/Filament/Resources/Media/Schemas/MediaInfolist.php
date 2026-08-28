<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Models\Media;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MediaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('disk'),
                TextEntry::make('path'),
                TextEntry::make('webp_path')
                    ->placeholder('-'),
                TextEntry::make('source_path')
                    ->placeholder('-'),
                TextEntry::make('original_name'),
                TextEntry::make('mime_type'),
                TextEntry::make('size')
                    ->numeric(),
                TextEntry::make('width')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('height')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('sha256'),
                TextEntry::make('alt')
                    ->placeholder('-'),
                TextEntry::make('caption')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('uploaded_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Media $record): bool => $record->trashed()),
                TextEntry::make('avif_path')
                    ->placeholder('-'),
                TextEntry::make('variants')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('title')
                    ->placeholder('-'),
            ]);
    }
}
