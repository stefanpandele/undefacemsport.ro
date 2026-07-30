<?php

namespace App\Filament\Admin\Resources\LocationClaims\Pages;

use App\Enums\LocationClaimStatus;
use App\Filament\Admin\Resources\LocationClaims\LocationClaimResource;
use App\Models\LocationClaim;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageLocationClaims extends ManageRecords
{
    protected static string $resource = LocationClaimResource::class;

    /**
     * Claims only ever arrive from an organization, so there is no create action.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('În așteptare')
                ->badge(fn (): int => LocationClaim::query()->where('status', LocationClaimStatus::Pending)->count())
                ->deferBadge()
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationClaimStatus::Pending)),
            'approved' => Tab::make('Aprobate')
                ->badge(fn (): int => LocationClaim::query()->where('status', LocationClaimStatus::Approved)->count())
                ->deferBadge()
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationClaimStatus::Approved)),
            'rejected' => Tab::make('Respinse')
                ->badge(fn (): int => LocationClaim::query()->where('status', LocationClaimStatus::Rejected)->count())
                ->deferBadge()
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationClaimStatus::Rejected)),
            'all' => Tab::make('Toate')
                ->badge(fn (): int => LocationClaim::query()->count())
                ->deferBadge(),
        ];
    }
}
