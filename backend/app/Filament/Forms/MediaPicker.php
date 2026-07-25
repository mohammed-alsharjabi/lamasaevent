<?php

namespace App\Filament\Forms;

use App\Models\Media;
use App\Services\ImageProcessor;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

class MediaPicker
{
    /**
     * @return array{Select, Placeholder}
     */
    public static function make(
        string $field = 'hero_media_id',
        string $label = 'الصورة البارزة',
        string $relationship = 'heroMedia',
        bool $required = false,
    ): array {
        return [
            Select::make($field)
                ->label($label)
                ->relationship($relationship, 'original_name')
                ->getOptionLabelFromRecordUsing(
                    fn (Media $record): string => self::optionLabel($record),
                )
                ->searchable(['original_name', 'title', 'alt'])
                ->preload()
                ->allowHtml()
                ->live()
                ->required($required)
                ->helperText('اختر صورة من المكتبة أو استخدم زر رفع صورة جديدة.')
                ->createOptionForm(self::uploadForm())
                ->createOptionUsing(function (array $data): int {
                    $media = app(ImageProcessor::class)->upload(
                        $data['upload'],
                        auth()->id(),
                    );
                    $media->update([
                        'title' => $data['title'] ?? null,
                        'alt' => $data['alt'],
                        'caption' => $data['caption'] ?? null,
                    ]);

                    return (int) $media->getKey();
                })
                ->createOptionAction(
                    fn (Action $action): Action => $action
                        ->label('رفع صورة جديدة')
                        ->button()
                        ->modalHeading('رفع صورة جديدة إلى المكتبة')
                        ->modalSubmitActionLabel('رفع واختيار الصورة'),
                ),
            Placeholder::make($field.'_preview')
                ->label('الصورة المختارة')
                ->content(
                    fn (Get $get): HtmlString => self::preview(
                        Media::query()->find($get($field)),
                    ),
                )
                ->visible(fn (Get $get): bool => filled($get($field)))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, FileUpload|TextInput|Textarea>
     */
    public static function uploadForm(): array
    {
        return [
            FileUpload::make('upload')
                ->label('ملف الصورة')
                ->image()
                ->storeFiles(false)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(8192)
                ->required()
                ->columnSpanFull(),
            TextInput::make('title')
                ->label('عنوان الصورة')
                ->maxLength(255),
            TextInput::make('alt')
                ->label('النص البديل')
                ->helperText('وصف مختصر للصورة لتحسين الوصول وSEO.')
                ->required()
                ->maxLength(255),
            Textarea::make('caption')
                ->label('وصف الصورة')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    public static function preview(?Media $media): HtmlString
    {
        if (! $media) {
            return new HtmlString('<span class="text-sm text-gray-500">لم تُحدد صورة.</span>');
        }

        $url = e(self::url($media));
        $name = e($media->original_name);
        $alt = e($media->alt ?: $media->original_name);
        $dimensions = e("{$media->width} × {$media->height}");

        return new HtmlString(
            '<div style="display:flex;align-items:center;gap:1rem">'
            .'<img src="'.$url.'" alt="'.$alt.'" '
            .'style="width:9rem;height:6rem;object-fit:cover;border-radius:.75rem;'
            .'border:1px solid #d1d5db;background:#f9fafb">'
            .'<div><strong style="display:block">'.$name.'</strong>'
            .'<small style="color:#6b7280">'.$dimensions.'</small></div></div>',
        );
    }

    private static function optionLabel(Media $media): string
    {
        $url = e(self::url($media));
        $name = e($media->original_name);
        $alt = e($media->alt ?: $media->original_name);

        return '<div style="display:flex;align-items:center;gap:.65rem">'
            .'<img src="'.$url.'" alt="'.$alt.'" '
            .'style="width:2.5rem;height:2.5rem;object-fit:cover;border-radius:.4rem">'
            .'<span>'.$name.'</span></div>';
    }

    private static function url(Media $media): string
    {
        return $media->url();
    }
}
