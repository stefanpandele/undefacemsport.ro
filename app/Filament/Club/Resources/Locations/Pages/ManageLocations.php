<?php

namespace App\Filament\Club\Resources\Locations\Pages;

use App\Filament\Club\Resources\Locations\LocationResource;
use App\Models\ClubLocation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLocations extends ManageRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->closeModalByClickingAway(false)
                ->using(fn (array $data): ClubLocation => LocationResource::persist($data)),
        ];
    }
}
