<?php

namespace App\Filament\Admin\Resources\ClubApplications\Pages;

use App\Filament\Admin\Resources\ClubApplications\ClubApplicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClubApplication extends ViewRecord
{
    protected static string $resource = ClubApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
