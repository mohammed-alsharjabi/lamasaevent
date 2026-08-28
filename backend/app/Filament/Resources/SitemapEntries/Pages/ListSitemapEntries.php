<?php

namespace App\Filament\Resources\SitemapEntries\Pages;

use App\Filament\Resources\SitemapEntries\SitemapEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSitemapEntries extends ListRecords
{
    protected static string $resource = SitemapEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
