<?php

namespace App\Filament\Resources\PublishJobs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PublishJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('publishable_type'),
                TextInput::make('publishable_id')
                    ->numeric(),
                TextInput::make('action')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('scheduled_for'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
                TextInput::make('snapshot_path'),
                TextInput::make('build_version'),
                Textarea::make('failure_reason')
                    ->columnSpanFull(),
                TextInput::make('attempts')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('requested_by')
                    ->numeric(),
            ]);
    }
}
