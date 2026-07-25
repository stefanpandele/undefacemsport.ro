<?php

namespace App\Filament\Admin\Resources\Clubs;

use App\Filament\Admin\Resources\Clubs\Pages\CreateClub;
use App\Filament\Admin\Resources\Clubs\Pages\EditClub;
use App\Filament\Admin\Resources\Clubs\Pages\ListClubs;
use App\Filament\Admin\Resources\Clubs\Pages\ViewClub;
use App\Filament\Admin\Resources\Clubs\Schemas\ClubForm;
use App\Filament\Admin\Resources\Clubs\Schemas\ClubInfolist;
use App\Filament\Admin\Resources\Clubs\Tables\ClubsTable;
use App\Models\Club;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ClubForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClubInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClubsTable::configure($table);
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
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'view' => ViewClub::route('/{record}'),
            'edit' => EditClub::route('/{record}/edit'),
        ];
    }
}
