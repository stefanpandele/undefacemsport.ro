<?php

namespace App\Filament\Admin\Resources\OrganizationApplications\Pages;

use App\Filament\Admin\Resources\OrganizationApplications\OrganizationApplicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganizationApplication extends ViewRecord
{
    protected static string $resource = OrganizationApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
