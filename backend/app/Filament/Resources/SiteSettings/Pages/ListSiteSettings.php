<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListSiteSettings extends ListRecords
{
    protected static string $resource = SiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'اختر القسم الذي تريد تغييره. ستجد داخل كل قسم وصفًا واضحًا لمكان ظهور كل قيمة في الموقع.';
    }
}
