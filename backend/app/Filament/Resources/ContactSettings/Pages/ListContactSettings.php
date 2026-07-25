<?php

namespace App\Filament\Resources\ContactSettings\Pages;

use App\Filament\Resources\ContactSettings\ContactSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContactSettings extends ListRecords
{
    protected static string $resource = ContactSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
