<?php

namespace App\Filament\Admin\Resources\Clubs\Pages;

use App\Filament\Admin\Resources\Clubs\ClubResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClub extends ViewRecord
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
