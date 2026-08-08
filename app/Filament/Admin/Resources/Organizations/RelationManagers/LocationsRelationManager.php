<?php

namespace App\Filament\Admin\Resources\Organizations\RelationManagers;

use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Models\OrganizationLocation;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'organizationLocations';

    protected static ?string $title = 'Locații';

    public function form(Schema $schema): Schema
    {
        return LocationResource::form($schema);
    }

    /**
     * The same table the organization's own panel shows, grouped by county.
     *
     * Grouping is the whole addition: the header of each group carries the count,
     * so "how many halls in Cluj" is read rather than tallied, and each row
     * already lists the sports taught at that address.
     */
    public function table(Table $table): Table
    {
        return LocationResource::table($table)
            ->groups([
                Group::make('location.county')
                    ->label('Județ')
                    ->collapsible(),
            ])
            ->defaultGroup('location.county')
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, $livewire): OrganizationLocation => LocationResource::persist($data, null, $livewire)),
            ]);
    }
}
