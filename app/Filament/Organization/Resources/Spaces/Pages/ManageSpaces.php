<?php

namespace App\Filament\Organization\Resources\Spaces\Pages;

use App\Filament\Organization\Resources\Spaces\SpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Component;

class ManageSpaces extends ManageRecords
{
    protected static string $resource = SpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Never gated on the plan limit: an organization at its quota can
            // still put a public space on the map, and the toggle in the form is
            // what refuses to make it theirs.
            CreateAction::make()
                ->mutateDataUsing(fn (array $data, ?Component $livewire): array => SpaceResource::resolveOwnership($data, $livewire)),
        ];
    }
}
