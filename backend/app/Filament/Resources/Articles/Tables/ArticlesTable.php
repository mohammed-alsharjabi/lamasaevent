<?php

namespace App\Filament\Resources\Articles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('العنوان')->searchable()->limit(55),
                TextColumn::make('category.name')->label('التصنيف')->placeholder('—'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->value === 'published' ? 'منشور' : 'مسودة')
                    ->color(fn ($state): string => $state->value === 'published' ? 'success' : 'gray'),
                IconColumn::make('is_featured')->label('مميز')->boolean(),
                TextColumn::make('published_at')->label('تاريخ النشر')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('updated_at')->label('آخر تعديل')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options([
                    'draft' => 'مسودة',
                    'published' => 'منشور',
                ]),
                SelectFilter::make('article_category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name'),
                TrashedFilter::make()->label('المحذوفات'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()->label('حذف'),
                RestoreAction::make()->label('استعادة'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
