<?php

namespace App\Filament\Club\Resources\ScheduleSlots\Pages;

use App\Filament\Club\Resources\ScheduleSlots\ScheduleSlotResource;
use App\Models\ScheduleSlot;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageScheduleSlots extends ManageRecords
{
    protected static string $resource = ScheduleSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Adaugă intervale')
                ->modalHeading('Adaugă intervale în orar')
                // Five fields per row need the room.
                ->modalWidth(Width::FiveExtraLarge)
                ->createAnother(false)
                ->schema(ScheduleSlotResource::bulkFormComponents())
                ->using(fn (array $data): ?ScheduleSlot => ScheduleSlotResource::persistMany($data)),
        ];
    }
}
