<?php

namespace App\Filament\Resources\PublishJobs\Pages;

use App\Filament\Resources\PublishJobs\PublishJobResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPublishJob extends ViewRecord
{
    protected static string $resource = PublishJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
