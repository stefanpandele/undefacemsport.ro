<?php

namespace App\Filament\Admin\Resources\LocationCorrections\Pages;

use App\Enums\LocationCorrectionStatus;
use App\Filament\Admin\Resources\LocationCorrections\LocationCorrectionResource;
use App\Models\LocationCorrection;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageLocationCorrections extends ManageRecords
{
    protected static string $resource = LocationCorrectionResource::class;

    /**
     * Corrections are never created here — they only ever arrive from a club that
     * found a shared location wrong, so there is no create action.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('În așteptare')
                ->badge(fn (): int => LocationCorrection::query()->where('status', LocationCorrectionStatus::Pending)->count())
                ->deferBadge()
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationCorrectionStatus::Pending)),
            'approved' => Tab::make('Aplicate')
                ->badge(fn (): int => LocationCorrection::query()->where('status', LocationCorrectionStatus::Approved)->count())
                ->deferBadge()
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationCorrectionStatus::Approved)),
            'rejected' => Tab::make('Respinse')
                ->badge(fn (): int => LocationCorrection::query()->where('status', LocationCorrectionStatus::Rejected)->count())
                ->deferBadge()
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationCorrectionStatus::Rejected)),
            'all' => Tab::make('Toate')
                ->badge(fn (): int => LocationCorrection::query()->count())
                ->deferBadge(),
        ];
    }
}
