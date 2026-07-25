<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Filament\Resources\Concerns\HasSlugRedirectAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    use HandlesManagedContent, HasSlugRedirectAction;

    protected static string $resource = ArticleResource::class;

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
