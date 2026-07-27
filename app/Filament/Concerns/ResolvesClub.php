<?php

namespace App\Filament\Concerns;

use App\Models\Club;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Livewire\Component;

/**
 * Resolves "the current club" for a resource used both on the tenant-scoped
 * club panel and as an admin relation manager under the Club resource.
 */
trait ResolvesClub
{
    protected static function resolveClub(?Component $livewire = null): ?Club
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Club) {
            return $tenant;
        }

        if ($livewire instanceof RelationManager) {
            $owner = $livewire->getOwnerRecord();

            if ($owner instanceof Club) {
                return $owner;
            }
        }

        return null;
    }
}
