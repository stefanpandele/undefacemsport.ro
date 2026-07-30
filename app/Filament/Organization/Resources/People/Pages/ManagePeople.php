<?php

namespace App\Filament\Organization\Resources\People\Pages;

use App\Filament\Organization\Resources\People\PersonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePeople extends ManageRecords
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
