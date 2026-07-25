<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('action')
                    ->required(),
                TextInput::make('subject_type'),
                TextInput::make('subject_id')
                    ->numeric(),
                Textarea::make('before')
                    ->columnSpanFull(),
                Textarea::make('after')
                    ->columnSpanFull(),
                TextInput::make('ip_address'),
                TextInput::make('user_agent'),
            ]);
    }
}
