<?php

namespace App\Filament\Admin\Resources\Clubs\Pages;

use App\Filament\Admin\Resources\Clubs\ClubResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditClub extends EditRecord
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
