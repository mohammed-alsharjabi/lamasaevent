<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'لوحة المعلومات';

    protected static ?string $title = 'لوحة معلومات لمسة';

    protected static ?int $navigationSort = -10;
}
