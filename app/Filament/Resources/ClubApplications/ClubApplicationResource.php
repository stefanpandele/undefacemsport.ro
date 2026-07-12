<?php

namespace App\Filament\Resources\ClubApplications;

use App\Filament\Resources\ClubApplications\Pages\CreateClubApplication;
use App\Filament\Resources\ClubApplications\Pages\EditClubApplication;
use App\Filament\Resources\ClubApplications\Pages\ListClubApplications;
use App\Filament\Resources\ClubApplications\Pages\ViewClubApplication;
use App\Filament\Resources\ClubApplications\Schemas\ClubApplicationForm;
use App\Filament\Resources\ClubApplications\Schemas\ClubApplicationInfolist;
use App\Filament\Resources\ClubApplications\Tables\ClubApplicationsTable;
use App\Models\ClubApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClubApplicationResource extends Resource
{
    protected static ?string $model = ClubApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ClubApplicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClubApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClubApplicationsTable::configure($table);
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
            'index' => ListClubApplications::route('/'),
            'create' => CreateClubApplication::route('/create'),
            'view' => ViewClubApplication::route('/{record}'),
            'edit' => EditClubApplication::route('/{record}/edit'),
        ];
    }
}
