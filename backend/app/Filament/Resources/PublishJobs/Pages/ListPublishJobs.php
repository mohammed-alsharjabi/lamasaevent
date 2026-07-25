<?php

namespace App\Filament\Resources\PublishJobs\Pages;

use App\Filament\Resources\PublishJobs\PublishJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublishJobs extends ListRecords
{
    protected static string $resource = PublishJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
