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
                TextInput::make('phone')->label('الهاتف')->tel(),
                TextInput::make('phone_display')->label('الهاتف الظاهر'),
                TextInput::make('whatsapp')->label('واتساب'),
                TextInput::make('email')->label('البريد الإلكتروني')->email(),
                TextInput::make('city')->label('المدينة'),
                TextInput::make('region')->label('المنطقة'),
                TextInput::make('country_code')->label('رمز الدولة')->length(2),
                KeyValue::make('social_links')
                    ->label('روابط الشبكات الاجتماعية')
                    ->keyLabel('الشبكة')
                    ->valueLabel('الرابط')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
