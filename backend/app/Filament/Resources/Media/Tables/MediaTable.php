<?php

namespace App\Filament\Resources\Media\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('webp_path')->label('معاينة')->disk(fn ($record) => $record->disk)->square(),
                TextColumn::make('original_name')->label('اسم الملف')->searchable()->limit(40),
                TextColumn::make('alt')->label('النص البديل')->searchable()->limit(45),
                TextColumn::make('mime_type')->label('النوع')->badge(),
                TextColumn::make('size')->label('الحجم')->formatStateUsing(
                    fn (int $state): string => number_format($state / 1024, 1).' KB',
                ),
                TextColumn::make('created_at')->label('تاريخ الرفع')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([TrashedFilter::make()->label('المحذوفات')])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
