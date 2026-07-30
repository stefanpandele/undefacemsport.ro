<?php

namespace App\Filament\Admin\Resources\Organizations\RelationManagers;

use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Models\OrganizationLocation;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'organizationLocations';

    protected static ?string $title = 'Locații';

    public function form(Schema $schema): Schema
    {
        return LocationResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return LocationResource::table($table)
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, $livewire): OrganizationLocation => LocationResource::persist($data, null, $livewire)),
            ]);
    }
}
