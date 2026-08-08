<?php

namespace App\Filament\Admin\Resources\Organizations\Tables;

use App\Models\Location;
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
                ->withCount('organizationLocations as locations_count')
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
