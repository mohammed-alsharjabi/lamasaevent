<?php

namespace App\Filament\Resources\PublishJobs;

use App\Filament\Resources\PublishJobs\Pages\CreatePublishJob;
use App\Filament\Resources\PublishJobs\Pages\EditPublishJob;
use App\Filament\Resources\PublishJobs\Pages\ListPublishJobs;
use App\Filament\Resources\PublishJobs\Pages\ViewPublishJob;
use App\Filament\Resources\PublishJobs\Schemas\PublishJobForm;
use App\Filament\Resources\PublishJobs\Schemas\PublishJobInfolist;
use App\Filament\Resources\PublishJobs\Tables\PublishJobsTable;
use App\Models\PublishJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PublishJobResource extends Resource
{
    protected static ?string $model = PublishJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'build_version';

    public static function form(Schema $schema): Schema
    {
        return PublishJobForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PublishJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublishJobsTable::configure($table);
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
            'index' => ListPublishJobs::route('/'),
            'create' => CreatePublishJob::route('/create'),
            'view' => ViewPublishJob::route('/{record}'),
            'edit' => EditPublishJob::route('/{record}/edit'),
        ];
    }
}
