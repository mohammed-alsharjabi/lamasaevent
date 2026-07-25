<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('المستخدم')->placeholder('النظام'),
                TextColumn::make('action')->label('النشاط')->badge()->searchable(),
                TextColumn::make('subject_type')->label('نوع السجل')->formatStateUsing(
                    fn (?string $state): string => $state ? class_basename($state) : '—',
                ),
                TextColumn::make('subject_id')->label('المعرّف')->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->searchable(),
                TextColumn::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
