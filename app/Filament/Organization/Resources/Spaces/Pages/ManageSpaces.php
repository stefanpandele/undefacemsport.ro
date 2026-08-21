<?php

namespace App\Filament\Organization\Resources\Spaces\Pages;

use App\Filament\Organization\Resources\Spaces\SpaceResource;
use App\Models\Space;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
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
            // Six squash courts are six rows, and typing six identical forms is
            // what that rule costs. This pays it once.
            CreateAction::make('createMany')
                ->label('Adaugă mai multe')
                ->modalHeading('Adaugă mai multe terenuri deodată')
                ->modalWidth(Width::FiveExtraLarge)
                ->createAnother(false)
                ->schema(SpaceResource::bulkFormComponents())
                ->using(fn (array $data, ?Component $livewire): Space => SpaceResource::persistMany($data, $livewire)),
        ];
    }
}
