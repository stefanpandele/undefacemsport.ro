<?php

namespace App\Filament\Admin\Resources\ClubApplications\Pages;

use App\Filament\Admin\Resources\ClubApplications\ClubApplicationResource;
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
