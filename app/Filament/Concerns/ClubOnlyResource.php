<?php

namespace App\Filament\Concerns;

use App\Models\Organization;
use Filament\Facades\Filament;

/**
 * Hides a resource from tenants that are not clubs.
 *
 * The panel is shared by every kind of organization, but training programmes —
 * sports, weekly schedules, coaching staff — only mean something for a club. A
 * venue renting out courts has none of them, and a practice offers services
 * instead. `Locations` deliberately does not use this: every organization needs
 * an address.
 *
 * Both hooks are needed: navigation alone would hide the link while leaving the
 * URL reachable.
 */
trait ClubOnlyResource
{
    public static function canAccess(): bool
    {
        return static::tenantIsClub() && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::tenantIsClub() && parent::shouldRegisterNavigation();
    }

    protected static function tenantIsClub(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Organization && $tenant->isClub();
    }
}
