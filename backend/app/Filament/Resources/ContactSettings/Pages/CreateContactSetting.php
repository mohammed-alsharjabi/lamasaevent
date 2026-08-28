<?php

namespace App\Filament\Resources\ContactSettings\Pages;

use App\Filament\Resources\ContactSettings\ContactSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContactSetting extends CreateRecord
{
    protected static string $resource = ContactSettingResource::class;
}
