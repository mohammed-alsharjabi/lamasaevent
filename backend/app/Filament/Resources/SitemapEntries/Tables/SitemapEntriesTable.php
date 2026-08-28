<?php

namespace App\Filament\Resources\SitemapEntries\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitemapEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->numeric()->sortable(),
                TextColumn::make('path')->label('المسار المحمي')->searchable()->copyable(),
                TextColumn::make('lastmod')->label('آخر تعديل')->date()->sortable(),
                TextColumn::make('changefreq')->label('معدل التغيير')->placeholder('—'),
                TextColumn::make('priority')->label('الأولوية')->numeric(),
                IconColumn::make('is_included')->label('مضمن')->boolean(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
