<?php

namespace App\Filament\Admin\Resources\OrganizationApplications;

use App\Filament\Admin\Resources\OrganizationApplications\Pages\CreateOrganizationApplication;
use App\Filament\Admin\Resources\OrganizationApplications\Pages\EditOrganizationApplication;
use App\Filament\Admin\Resources\OrganizationApplications\Pages\ListOrganizationApplications;
use App\Filament\Admin\Resources\OrganizationApplications\Pages\ViewOrganizationApplication;
use App\Filament\Admin\Resources\OrganizationApplications\Schemas\OrganizationApplicationForm;
use App\Filament\Admin\Resources\OrganizationApplications\Schemas\OrganizationApplicationInfolist;
use App\Filament\Admin\Resources\OrganizationApplications\Tables\OrganizationApplicationsTable;
use App\Models\OrganizationApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrganizationApplicationResource extends Resource
{
    protected static ?string $model = OrganizationApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OrganizationApplicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganizationApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationApplicationsTable::configure($table);
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
            'index' => ListOrganizationApplications::route('/'),
            'create' => CreateOrganizationApplication::route('/create'),
            'view' => ViewOrganizationApplication::route('/{record}'),
            'edit' => EditOrganizationApplication::route('/{record}/edit'),
        ];
    }
}
