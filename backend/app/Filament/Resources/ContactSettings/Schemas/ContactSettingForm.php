<?php

namespace App\Filament\Resources\ContactSettings\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات التواصل')->columns(2)->schema([
                TextInput::make('phone')->label('الهاتف')->tel()->maxLength(30),
                TextInput::make('phone_display')->label('الهاتف الظاهر')->maxLength(30),
                TextInput::make('whatsapp')->label('واتساب')->maxLength(30),
                TextInput::make('email')->label('البريد الإلكتروني')->email()->maxLength(255),
                TextInput::make('city')->label('المدينة')->maxLength(100),
                TextInput::make('region')->label('المنطقة')->maxLength(100),
                TextInput::make('country_code')
                    ->label('رمز الدولة')
                    ->length(2)
                    ->dehydrateStateUsing(
                        fn (?string $state): ?string => $state ? strtoupper($state) : null,
                    ),
                KeyValue::make('social_links')
                    ->label('روابط الشبكات الاجتماعية')
                    ->keyLabel('الشبكة')
                    ->valueLabel('الرابط')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
