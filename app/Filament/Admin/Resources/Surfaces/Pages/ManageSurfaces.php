<?php

namespace App\Filament\Admin\Resources\Surfaces\Pages;

use App\Filament\Admin\Resources\Surfaces\SurfaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSurfaces extends ManageRecords
{
    protected static string $resource = SurfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
