<?php

namespace App\Filament\Organization\Resources\Locations\Pages;

use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Models\OrganizationLocation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLocations extends ManageRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): OrganizationLocation => LocationResource::persist($data)),
        ];
    }
}
