<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('الاسم')->searchable(),
            TextColumn::make('email')->label('البريد الإلكتروني')->searchable(),
            TextColumn::make('roles.name')->label('الأدوار')->badge(),
            IconColumn::make('is_active')->label('نشط')->boolean(),
            TextColumn::make('last_login_at')->label('آخر دخول')->since()->placeholder('—'),
            TextColumn::make('failed_login_count')->label('محاولات فاشلة')->numeric(),
            TextColumn::make('locked_until')->label('مقفل حتى')->dateTime()->placeholder('—'),
        ])->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
