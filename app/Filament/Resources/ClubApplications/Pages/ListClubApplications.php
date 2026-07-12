<?php

namespace App\Filament\Resources\ClubApplications\Pages;

use App\Filament\Resources\ClubApplications\ClubApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClubApplications extends ListRecords
{
    protected static string $resource = ClubApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
