<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Filament\Resources\Concerns\HasSlugRedirectAction;
use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    use HandlesManagedContent, HasSlugRedirectAction;

    protected static string $resource = ServiceResource::class;

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
