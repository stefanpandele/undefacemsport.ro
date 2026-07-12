<?php

namespace App\Filament\Resources\ClubApplications\Pages;

use App\Filament\Resources\ClubApplications\ClubApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditClubApplication extends EditRecord
{
    protected static string $resource = ClubApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
