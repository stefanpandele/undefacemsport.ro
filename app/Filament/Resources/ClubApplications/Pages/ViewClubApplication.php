<?php

namespace App\Filament\Resources\ClubApplications\Pages;

use App\Filament\Resources\ClubApplications\ClubApplicationResource;
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
