<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('إعداد الموقع')->columns(2)->schema([
                TextInput::make('key')->label('المفتاح')->unique(ignoreRecord: true)->required(),
                Select::make('group')->label('المجموعة')->options([
                    'general' => 'عام',
                    'seo' => 'SEO',
                    'social' => 'شبكات اجتماعية',
                    'publishing' => 'النشر',
                ])->default('general')->required(),
                KeyValue::make('value')
                    ->label('القيمة المنظمة')
                    ->keyLabel('المفتاح')
                    ->valueLabel('القيمة')
                    ->columnSpanFull(),
                Toggle::make('is_public')->label('متاح للواجهة العامة'),
                Toggle::make('is_sensitive')->label('بيان حساس'),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
