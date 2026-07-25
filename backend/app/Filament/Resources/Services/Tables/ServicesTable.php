<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('الخدمة')->searchable()->limit(55),
                TextColumn::make('service_level')
                    ->label('النوع')
                    ->state(fn (Service $record): string => $record->parent_id ? 'فرعية' : 'رئيسية')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'رئيسية' ? 'primary' : 'gray'),
                TextColumn::make('parent.title')
                    ->label('الخدمة الرئيسية')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('category.title')->label('التصنيف')->placeholder('—'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->value === 'published' ? 'منشور' : 'مسودة')
                    ->color(fn ($state): string => $state->value === 'published' ? 'success' : 'gray'),
                IconColumn::make('is_featured')->label('مميزة')->boolean(),
                TextColumn::make('sort_order')->label('الترتيب')->sortable(),
                TextColumn::make('updated_at')->label('آخر تعديل')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options([
                    'draft' => 'مسودة',
                    'published' => 'منشور',
                ]),
                SelectFilter::make('service_category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'title'),
                SelectFilter::make('service_level')
                    ->label('نوع الخدمة')
                    ->options([
                        'main' => 'خدمات رئيسية',
                        'child' => 'خدمات فرعية',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'main' => $query->whereNull('parent_id'),
                            'child' => $query->whereNotNull('parent_id'),
                            default => $query,
                        };
                    }),
                TrashedFilter::make()->label('المحذوفات'),
            ])
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
