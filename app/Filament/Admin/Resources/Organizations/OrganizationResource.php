<?php

namespace App\Filament\Admin\Resources\Organizations;

use App\Filament\Admin\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Admin\Resources\Organizations\Pages\ViewOrganization;
use App\Filament\Admin\Resources\Organizations\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\OrganizationSportsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\PeopleRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\ScheduleSlotsRelationManager;
use App\Filament\Admin\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Admin\Resources\Organizations\Schemas\OrganizationInfolist;
use App\Filament\Admin\Resources\Organizations\Tables\OrganizationsTable;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OrganizationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganizationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OrganizationSportsRelationManager::class,
            LocationsRelationManager::class,
            PeopleRelationManager::class,
            ScheduleSlotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'view' => ViewOrganization::route('/{record}'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
