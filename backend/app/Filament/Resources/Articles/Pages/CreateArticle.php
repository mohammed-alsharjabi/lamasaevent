<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Concerns\HandlesManagedContent;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    use HandlesManagedContent;

    protected static string $resource = ArticleResource::class;
}
