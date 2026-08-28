<?php

namespace App\Filament\Resources\SitemapEntries\Pages;

use App\Filament\Resources\SitemapEntries\SitemapEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSitemapEntry extends CreateRecord
{
    protected static string $resource = SitemapEntryResource::class;
}
