<?php

namespace App\Filament\Resources\ContactSettings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ContactSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('phone_display')
                    ->placeholder('-'),
                TextEntry::make('whatsapp')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('city')
                    ->placeholder('-'),
                TextEntry::make('region')
                    ->placeholder('-'),
                TextEntry::make('country_code')
                    ->placeholder('-'),
                TextEntry::make('social_links')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
