<?php

namespace App\Filament\Resources\SiteSettings\Tables;

use App\Filament\Support\SiteSettingPresentation;
use App\Models\SiteSetting;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('القسم')
                    ->formatStateUsing(
                        fn (?string $state): string => SiteSettingPresentation::title($state),
                    )
                    ->description(
                        fn (SiteSetting $record): string => SiteSettingPresentation::group(
                            $record->key,
                        ),
                    )
                    ->searchable(),
                TextColumn::make('group')
                    ->label('أين يظهر؟')
                    ->formatStateUsing(
                        fn (SiteSetting $record): string => SiteSettingPresentation::location(
                            $record->key,
                        ),
                    )
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('تعديل'),
            ])
            ->toolbarActions([]);
    }
}
