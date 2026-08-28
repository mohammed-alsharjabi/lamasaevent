<?php

namespace App\Filament\Resources\Menus\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('القائمة')->columns(2)->schema([
                TextInput::make('name')->label('الاسم')->required(),
                TextInput::make('location')->label('الموقع البرمجي')->unique(ignoreRecord: true)->required(),
                Toggle::make('is_active')->label('نشطة')->default(true),
                Repeater::make('allItems')
                    ->label('عناصر القائمة')
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->defaultItems(0)
                    ->schema([
                        TextInput::make('label')->label('النص')->required(),
                        TextInput::make('url')->label('الرابط')->required()->maxLength(2048),
                        Toggle::make('is_external')->label('رابط خارجي'),
                        Toggle::make('open_in_new_tab')->label('فتح في نافذة جديدة'),
                        Toggle::make('is_active')->label('ظاهر')->default(true),
                    ])
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
