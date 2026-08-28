<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Services\ImageProcessor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $media = app(ImageProcessor::class)->upload($data['upload'], auth()->id());
        $media->update([
            'title' => $data['title'] ?? null,
            'alt' => $data['alt'],
            'caption' => $data['caption'] ?? null,
        ]);

        return $media;
    }
}
