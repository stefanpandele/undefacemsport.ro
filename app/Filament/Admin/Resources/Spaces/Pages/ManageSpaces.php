<?php

namespace App\Filament\Admin\Resources\Spaces\Pages;

use App\Enums\FacilityStatus;
use App\Filament\Admin\Resources\Spaces\SpaceResource;
use App\Models\Space;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageSpaces extends ManageRecords
{
    protected static string $resource = SpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Admin-created spaces are public by design: no organization operates
            // them, which is exactly what a park court is.
            CreateAction::make()
                ->label('Adaugă spațiu public'),
        ];
    }

    /**
     * Quick filters with counts. The two badges that matter are the proposals
     * waiting on a decision and the public records old enough to have gone wrong.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            // "All" first on purpose: the recurring job here is adding and
            // browsing public spaces, and both queues below are empty most days —
            // landing an admin on an empty screen would be the wrong greeting.
            // The badges are what make waiting work visible.
            'all' => Tab::make('Toate')
                ->badge(fn (): int => Space::query()->count())
                ->deferBadge(),
            'pending' => Tab::make('În așteptare')
                ->badge(fn (): int => Space::query()->where('status', FacilityStatus::Pending)->count())
                ->deferBadge()
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', FacilityStatus::Pending)),
            'stale' => Tab::make('De reverificat')
                ->badge(fn (): int => Space::query()->stale()->count())
                ->deferBadge()
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->stale()),
            'public' => Tab::make('Publice')
                ->badge(fn (): int => Space::query()->unmanaged()->count())
                ->deferBadge()
                ->modifyQueryUsing(fn ($query) => $query->unmanaged()),
            'managed' => Tab::make('Administrate')
                ->badge(fn (): int => Space::query()->managed()->count())
                ->deferBadge()
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->managed()),
        ];
    }
}
