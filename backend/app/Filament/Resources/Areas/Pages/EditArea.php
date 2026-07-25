<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Filament\Resources\Concerns\HasSlugRedirectAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditArea extends EditRecord
{
    use HandlesManagedContent, HasSlugRedirectAction;

    protected static string $resource = AreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->slugRedirectAction(),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
