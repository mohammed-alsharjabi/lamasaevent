<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Concerns\HandlesManagedContent;
use Filament\Resources\Pages\CreateRecord;

class CreateArea extends CreateRecord
{
    use HandlesManagedContent;

    protected static string $resource = AreaResource::class;
}
