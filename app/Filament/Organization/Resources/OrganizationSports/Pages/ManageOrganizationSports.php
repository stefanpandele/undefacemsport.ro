<?php

namespace App\Filament\Organization\Resources\OrganizationSports\Pages;

use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOrganizationSports extends ManageRecords
{
    protected static string $resource = OrganizationSportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
