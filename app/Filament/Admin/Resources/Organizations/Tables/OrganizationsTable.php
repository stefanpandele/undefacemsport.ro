<?php

namespace App\Filament\Admin\Resources\Organizations\Tables;

use App\Enums\FacilityStatus;
use App\Enums\LocationWay;
use App\Enums\SpaceAccessMode;
use App\Models\Location;
use App\Models\Organization;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Both counts come from the query, so they sort in SQL rather than
            // after the page has already been cut.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount([
                    'organizationLocations as locations_count',
                    'organizationSports as courses_count',
                    'services as services_count',
                    // A space is offered whichever ways its tariffs say — the hall
                    // rented by the hour that opens on Friday evenings is both.
                    // Counted the way the public pages read it, or the admin would
                    // see a venue the site does not.
                    'spaces as open_access_count' => fn (Builder $spaces) => $spaces
                        ->where('spaces.status', FacilityStatus::Approved)
                        ->whereHas('accessSlots', fn (Builder $slots) => $slots
                            ->where('schedule_slots.access_mode', SpaceAccessMode::OpenAccess)),
                    'spaces as rental_count' => fn (Builder $spaces) => $spaces
                        ->where('spaces.status', FacilityStatus::Approved)
                        ->whereHas('accessSlots', fn (Builder $slots) => $slots
                            ->where('schedule_slots.access_mode', SpaceAccessMode::ExclusiveRental)),
                ])
                // Distinct, so the club with three halls in one town reads as
                // the one-county operation it is.
                ->addSelect(['counties_count' => Location::query()
                    ->selectRaw('count(distinct locations.county)')
                    ->join('organization_location', 'organization_location.location_id', '=', 'locations.id')
                    ->whereColumn('organization_location.organization_id', 'organizations.id'),
                ]))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('counties_count')
                    ->label('Prezență județe')
                    ->badge()
                    ->color(fn (int $state): string => $state > 1 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('locations_count')
                    ->label('Locații')
                    ->numeric()
                    ->sortable(),
                // What it actually offers, in the words the public side uses.
                // Only the kinds it has: an empty one is the absence of an offer,
                // not a zero worth showing.
                TextColumn::make('offers')
                    ->label('Oferă')
                    ->badge()
                    ->state(fn (Organization $record): array => collect([
                        LocationWay::Organised->label() => (int) $record->courses_count,
                        LocationWay::OpenAccess->label() => (int) $record->open_access_count,
                        LocationWay::Rental->label() => (int) $record->rental_count,
                        'Servicii' => (int) $record->services_count,
                    ])
                        ->filter()
                        ->map(fn (int $count, string $label): string => $count.' '.mb_strtolower($label))
                        ->values()
                        ->all())
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'cursuri') => 'success',
                        str_contains($state, 'agrement') => 'warning',
                        str_contains($state, 'închiriere') => 'info',
                        default => 'gray',
                    })
                    ->placeholder('nimic publicat'),
                TextColumn::make('owner_user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
