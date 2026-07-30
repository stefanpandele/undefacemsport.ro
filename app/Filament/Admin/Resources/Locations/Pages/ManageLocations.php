<?php

namespace App\Filament\Admin\Resources\Locations\Pages;

use App\Filament\Admin\Resources\Locations\LocationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLocations extends ManageRecords
{
    protected static string $resource = LocationResource::class;

    /**
     * No create action: a location is created by whoever first says they operate
     * or train at it, through the dedup flow in the tenant panel. Adding one here
     * would be a way around the very check that keeps the graph clean.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
