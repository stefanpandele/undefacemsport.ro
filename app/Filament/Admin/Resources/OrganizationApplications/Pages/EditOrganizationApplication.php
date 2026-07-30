<?php

namespace App\Filament\Admin\Resources\OrganizationApplications\Pages;

use App\Filament\Admin\Resources\OrganizationApplications\OrganizationApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganizationApplication extends EditRecord
{
    protected static string $resource = OrganizationApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
