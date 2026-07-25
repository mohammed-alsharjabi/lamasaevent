<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('المستخدم والصلاحيات')->columns(2)->schema([
                TextInput::make('name')->label('الاسم')->required(),
                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Toggle::make('is_active')->label('الحساب نشط')->default(true),
                CheckboxList::make('roles')
                    ->label('الأدوار')
                    ->relationship('roles', 'name')
                    ->columns(3)
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
