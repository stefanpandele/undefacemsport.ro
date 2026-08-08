<?php

namespace App\Filament\Organization\Widgets;

use App\Enums\LocationWay;
use App\Enums\OrganizationType;
use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use App\Filament\Organization\Resources\Services\ServiceResource;
use App\Filament\Organization\Resources\Spaces\SpaceResource;
use App\Models\Organization;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * What this organization can publish, in its own words.
 *
 * Nothing here is a switch. Ticking "I run courses" would be a second copy of a
 * fact the courses themselves already carry, and a copy can be wrong — so every
 * row is a door into the resource that makes the claim true. You become a club
 * by publishing a programme, and this only shows you where to do it.
 *
 * It also answers the question a freshly approved account arrives with: an
 * organization with nothing published has no public page at all, and until now
 * nothing on this screen said so.
 */
class OfferChecklistWidget extends Widget
{
    protected string $view = 'filament.organization.widgets.offer-checklist';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $organization = Filament::getTenant();

        if (! $organization instanceof Organization) {
            return ['address' => null, 'offers' => [], 'published' => false];
        }

        $locations = $organization->organizationLocations()->count();

        return [
            'address' => [
                'label' => 'Spune unde ești',
                'help' => 'Adresa vine prima: un spațiu sau un orar are nevoie de un loc.',
                'count' => $locations,
                'unit' => $locations === 1 ? 'locație' : 'locații',
                'url' => LocationResource::getUrl(),
                'icon' => '📍',
            ],
            'offers' => $this->offers($organization),
            'published' => $organization->offeredTypes() !== [],
        ];
    }

    /**
     * The three kinds of offer, each with what publishing one makes you.
     *
     * @return list<array<string, mixed>>
     */
    private function offers(Organization $organization): array
    {
        $sports = $organization->organizationSports()->count();
        $spaces = $organization->spaces()->count();
        $services = $organization->services()->count();

        return [
            [
                'key' => LocationWay::Organised->value,
                'label' => 'Țin cursuri',
                'help' => 'Grupe, niveluri și un orar săptămânal. Te face '.OrganizationType::Club->label().'.',
                'count' => $sports,
                'unit' => $sports === 1 ? 'sport' : 'sporturi',
                'url' => OrganizationSportResource::getUrl(),
                'icon' => '🏆',
                'done' => $sports > 0,
            ],
            [
                'key' => LocationWay::OpenAccess->value,
                'label' => 'Am spații în care se poate intra',
                'help' => 'Bazin, teren, sală — cu bilet sau închiriate cu ora. Te face '.OrganizationType::Venue->label().'.',
                'count' => $spaces,
                'unit' => $spaces === 1 ? 'spațiu' : 'spații',
                'url' => SpaceResource::getUrl(),
                'icon' => '🏟️',
                'done' => $spaces > 0,
            ],
            [
                'key' => 'servicii',
                'label' => 'Ofer servicii pe programare',
                'help' => 'Consultații, ședințe, evaluări. Te face '.OrganizationType::Practice->label().'.',
                'count' => $services,
                'unit' => $services === 1 ? 'serviciu' : 'servicii',
                'url' => ServiceResource::getUrl(),
                'icon' => '🩺',
                'done' => $services > 0,
            ],
        ];
    }
}
