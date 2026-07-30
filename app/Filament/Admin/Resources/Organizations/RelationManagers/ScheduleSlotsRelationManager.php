<?php

namespace App\Filament\Admin\Resources\Organizations\RelationManagers;

use App\Filament\Organization\Resources\ScheduleSlots\ScheduleSlotResource;
use App\Models\ScheduleSlot;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;

class ScheduleSlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduleSlots';

    protected static ?string $title = 'Orar';

    public function form(Schema $schema): Schema
    {
        return ScheduleSlotResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return ScheduleSlotResource::table($table)
            ->headerActions([
                CreateAction::make()
                    ->label('Adaugă intervale')
                    ->modalHeading('Adaugă intervale în orar')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->createAnother(false)
                    ->schema(ScheduleSlotResource::bulkFormComponents())
                    ->using(fn (array $data): ?ScheduleSlot => ScheduleSlotResource::persistMany($data)),
            ]);
    }
}
