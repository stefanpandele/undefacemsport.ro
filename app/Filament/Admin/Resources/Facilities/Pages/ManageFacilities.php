<?php

namespace App\Filament\Admin\Resources\Facilities\Pages;

use App\Enums\FacilityStatus;
use App\Filament\Admin\Resources\Facilities\FacilityResource;
use App\Models\Facility;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageFacilities extends ManageRecords
{
    protected static string $resource = FacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Quick filters with counts. The pending badge is the point: it tells an
     * admin at a glance whether any club is waiting on them.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toate')
                ->badge(fn (): int => Facility::query()->count())
                ->deferBadge(),
            'pending' => Tab::make('În așteptare')
                ->badge(fn (): int => Facility::query()->where('status', FacilityStatus::Pending)->count())
                ->deferBadge()
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', FacilityStatus::Pending)),
            'approved' => Tab::make('Aprobate')
                ->badge(fn (): int => Facility::query()->where('status', FacilityStatus::Approved)->count())
                ->deferBadge()
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', FacilityStatus::Approved)),
        ];
    }
}
