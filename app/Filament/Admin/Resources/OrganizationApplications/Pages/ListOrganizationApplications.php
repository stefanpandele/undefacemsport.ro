<?php

namespace App\Filament\Admin\Resources\OrganizationApplications\Pages;

use App\Enums\OrganizationApplicationStatus;
use App\Filament\Admin\Resources\OrganizationApplications\OrganizationApplicationResource;
use App\Models\OrganizationApplication;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrganizationApplications extends ListRecords
{
    protected static string $resource = OrganizationApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Pending first and by default: the queue exists to be emptied, and the
     * decided requests are history nobody opens the page for.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => $this->statusTab('În așteptare', OrganizationApplicationStatus::Pending),
            'approved' => $this->statusTab('Aprobate', OrganizationApplicationStatus::Approved),
            'rejected' => $this->statusTab('Respinse', OrganizationApplicationStatus::Rejected),
        ];
    }

    private function statusTab(string $label, OrganizationApplicationStatus $status): Tab
    {
        return Tab::make($label)
            ->badge(fn (): int => OrganizationApplication::query()->where('status', $status)->count())
            ->deferBadge()
            ->badgeColor($status->color())
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
    }
}
