<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RedirectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('from_path'),
                TextEntry::make('to_path'),
                TextEntry::make('status_code')
                    ->numeric(),
                TextEntry::make('reason')
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('hit_count')
                    ->numeric(),
                TextEntry::make('last_used_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
