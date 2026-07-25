<?php

namespace App\Filament\Resources\ContactSettings\Pages;

use App\Filament\Resources\ContactSettings\ContactSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditContactSetting extends EditRecord
{
    protected static string $resource = ContactSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
