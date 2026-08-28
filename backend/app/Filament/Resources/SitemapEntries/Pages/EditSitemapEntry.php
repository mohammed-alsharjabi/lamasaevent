<?php

namespace App\Filament\Resources\SitemapEntries\Pages;

use App\Filament\Resources\SitemapEntries\SitemapEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSitemapEntry extends EditRecord
{
    protected static string $resource = SitemapEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
