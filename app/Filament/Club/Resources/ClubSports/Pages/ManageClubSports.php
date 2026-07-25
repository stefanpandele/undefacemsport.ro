<?php

namespace App\Filament\Club\Resources\ClubSports\Pages;

use App\Filament\Club\Resources\ClubSports\ClubSportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageClubSports extends ManageRecords
{
    protected static string $resource = ClubSportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
