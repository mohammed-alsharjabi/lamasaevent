<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تحويل الرابط')->columns(2)->schema([
                TextInput::make('from_path')
                    ->label('المسار القديم')
                    ->startsWith('/')
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('to_path')
                    ->label('المسار الجديد')
                    ->startsWith('/')
                    ->different('from_path')
                    ->required(),
                Select::make('status_code')
                    ->label('رمز التحويل')
                    ->options([301 => '301 دائم', 302 => '302 مؤقت'])
                    ->default(301)
                    ->required(),
                TextInput::make('reason')->label('السبب'),
                Toggle::make('is_active')->label('نشط')->default(true),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
