<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentAudit extends TableWidget
{
    protected static ?string $heading = 'آخر النشاطات';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ActivityLog::query()->latest())
            ->columns([
                TextColumn::make('user.name')->label('المستخدم')->placeholder('النظام'),
                TextColumn::make('action')->label('النشاط')->badge()->searchable(),
                TextColumn::make('ip_address')->label('IP')->toggleable(),
                TextColumn::make('created_at')->label('الوقت')->since()->sortable(),
            ])
            ->paginated([5, 10]);
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('audit.view') ?? false;
    }
}
