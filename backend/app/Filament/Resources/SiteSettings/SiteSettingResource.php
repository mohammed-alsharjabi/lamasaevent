<?php

namespace App\Filament\Resources\SiteSettings;

use App\Filament\Resources\SiteSettings\Pages\CreateSiteSetting;
use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Filament\Resources\SiteSettings\Pages\ListSiteSettings;
use App\Filament\Resources\SiteSettings\Pages\ViewSiteSetting;
use App\Filament\Resources\SiteSettings\Schemas\SiteSettingForm;
use App\Filament\Resources\SiteSettings\Schemas\SiteSettingInfolist;
use App\Filament\Resources\SiteSettings\Tables\SiteSettingsTable;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?string $modelLabel = 'إعداد';

    protected static ?string $pluralModelLabel = 'إعدادات الموقع وSEO';

    protected static ?string $navigationLabel = 'إعدادات الموقع وSEO';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return 'SEO والنشر';
    }

    public static function form(Schema $schema): Schema
    {
        return SiteSettingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SiteSettingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteSettings::route('/'),
            'create' => CreateSiteSetting::route('/create'),
            'view' => ViewSiteSetting::route('/{record}'),
            'edit' => EditSiteSetting::route('/{record}/edit'),
        ];
    }
}
