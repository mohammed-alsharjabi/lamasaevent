<?php

namespace App\Filament\Resources\SitemapEntries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SitemapEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('إعداد رابط خريطة الموقع')->columns(2)->schema([
                TextInput::make('loc')
                    ->label('الرابط المحمي')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('path')
                    ->label('المسار المحمي')
                    ->disabled()
                    ->dehydrated(),
                DatePicker::make('lastmod')->label('آخر تعديل'),
                Select::make('changefreq')->label('معدل التغيير')->options([
                    'always' => 'دائمًا',
                    'hourly' => 'كل ساعة',
                    'daily' => 'يومي',
                    'weekly' => 'أسبوعي',
                    'monthly' => 'شهري',
                    'yearly' => 'سنوي',
                    'never' => 'لا يتغير',
                ]),
                TextInput::make('priority')->label('الأولوية')->numeric()->minValue(0)->maxValue(1),
                TextInput::make('position')->label('الترتيب')->numeric()->disabled()->dehydrated(),
                Toggle::make('is_included')
                    ->label('مضمن (مقفل لحماية روابط الاستعادة)')
                    ->disabled()
                    ->dehydrated(),
            ])->columnSpanFull(),
        ])->columns(1);
    }
}
