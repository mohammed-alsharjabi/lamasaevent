<?php

namespace App\Filament\Resources\PublishJobs\Pages;

use App\Filament\Resources\PublishJobs\PublishJobResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPublishJob extends EditRecord
{
    protected static string $resource = PublishJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
