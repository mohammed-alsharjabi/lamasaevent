<?php

namespace App\Filament\Resources\SitemapEntries\Pages;

use App\Filament\Resources\SitemapEntries\SitemapEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSitemapEntry extends ViewRecord
{
    protected static string $resource = SitemapEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
