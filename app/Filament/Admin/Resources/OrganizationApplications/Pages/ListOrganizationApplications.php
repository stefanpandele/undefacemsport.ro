<?php

namespace App\Filament\Admin\Resources\OrganizationApplications\Pages;

use App\Filament\Admin\Resources\OrganizationApplications\OrganizationApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationApplications extends ListRecords
{
    protected static string $resource = OrganizationApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
