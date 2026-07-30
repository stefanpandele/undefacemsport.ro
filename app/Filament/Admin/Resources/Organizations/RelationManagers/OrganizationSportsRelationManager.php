<?php

namespace App\Filament\Admin\Resources\Organizations\RelationManagers;

use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OrganizationSportsRelationManager extends RelationManager
{
    protected static string $relationship = 'organizationSports';

    protected static ?string $title = 'Sporturi';

    public function form(Schema $schema): Schema
    {
        return OrganizationSportResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return OrganizationSportResource::table($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
