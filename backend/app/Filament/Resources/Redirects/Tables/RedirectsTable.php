<?php

namespace App\Filament\Resources\Redirects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('from_path')->label('من')->searchable(),
            TextColumn::make('to_path')->label('إلى')->searchable(),
            TextColumn::make('status_code')->label('الرمز')->badge(),
            IconColumn::make('is_active')->label('نشط')->boolean(),
            TextColumn::make('hit_count')->label('الاستخدامات')->numeric()->sortable(),
            TextColumn::make('last_used_at')->label('آخر استخدام')->since()->placeholder('—'),
        ])->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
