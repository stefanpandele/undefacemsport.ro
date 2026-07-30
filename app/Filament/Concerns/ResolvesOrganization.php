<?php

namespace App\Filament\Concerns;

use App\Models\Organization;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Livewire\Component;

/**
 * Resolves "the current club" for a resource used both on the tenant-scoped
 * club panel and as an admin relation manager under the Organization resource.
 */
trait ResolvesOrganization
{
    protected static function resolveOrganization(?Component $livewire = null): ?Organization
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Organization) {
            return $tenant;
        }

        if ($livewire instanceof RelationManager) {
            $owner = $livewire->getOwnerRecord();

            if ($owner instanceof Organization) {
                return $owner;
            }
        }

        return null;
    }
}
