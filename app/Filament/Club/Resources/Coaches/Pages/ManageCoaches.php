<?php

namespace App\Filament\Club\Resources\Coaches\Pages;

use App\Filament\Club\Resources\Coaches\CoachResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCoaches extends ManageRecords
{
    protected static string $resource = CoachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
