<?php

namespace App\Filament\Resources\SitemapEntries;

use App\Filament\Resources\SitemapEntries\Pages\EditSitemapEntry;
use App\Filament\Resources\SitemapEntries\Pages\ListSitemapEntries;
use App\Filament\Resources\SitemapEntries\Pages\ViewSitemapEntry;
use App\Filament\Resources\SitemapEntries\Schemas\SitemapEntryForm;
use App\Filament\Resources\SitemapEntries\Schemas\SitemapEntryInfolist;
use App\Filament\Resources\SitemapEntries\Tables\SitemapEntriesTable;
use App\Models\SitemapEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SitemapEntryResource extends Resource
{
    protected static ?string $model = SitemapEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'path';

    protected static ?string $modelLabel = 'رابط خريطة';

    protected static ?string $pluralModelLabel = 'خريطة الموقع';

    protected static ?string $navigationLabel = 'خريطة الموقع';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return 'SEO والنشر';
    }

    public static function form(Schema $schema): Schema
    {
        return SitemapEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SitemapEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SitemapEntriesTable::configure($table);
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
            'index' => ListSitemapEntries::route('/'),
            'view' => ViewSitemapEntry::route('/{record}'),
            'edit' => EditSitemapEntry::route('/{record}/edit'),
        ];
    }
}
