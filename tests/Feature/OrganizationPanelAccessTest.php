<?php

use App\Enums\NavigationGroup;
use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use App\Filament\Organization\Resources\People\PersonResource;
use App\Filament\Organization\Resources\ScheduleSlots\ScheduleSlotResource;
use App\Filament\Organization\Resources\Services\ServiceResource;
use App\Filament\Organization\Resources\Spaces\SpaceResource;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;

test('a club member can open the club sports page without error', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member)
        ->get(OrganizationSportResource::getUrl(panel: 'organization', tenant: $organization))
        ->assertSuccessful();
});

test('the sidebar groups the three offers and leaves the two foundations above them', function () {
    // Locations and people are not a fourth offer: every course, space and
    // service hangs off an address, and the same people run the courses and
    // give the services.
    expect(LocationResource::getNavigationGroup())->toBeNull()
        ->and(PersonResource::getNavigationGroup())->toBeNull()
        ->and(PersonResource::getNavigationLabel())->toBe('Oameni')
        ->and(OrganizationSportResource::getNavigationGroup())->toBe(NavigationGroup::Courses)
        ->and(ScheduleSlotResource::getNavigationGroup())->toBe(NavigationGroup::Courses)
        ->and(SpaceResource::getNavigationGroup())->toBe(NavigationGroup::Leisure)
        ->and(ServiceResource::getNavigationGroup())->toBe(NavigationGroup::Services);
});

test('nothing is hidden from an organization that does not offer it yet', function () {
    // Offers are additive, and a club that wanted to start renting its hall
    // would never find the screen if the group only appeared once it already
    // had a space.
    $member = User::factory()->create();
    $organization = Organization::factory()->create(); // no offer of any kind

    $organization->addMember($member);
    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    expect(SpaceResource::shouldRegisterNavigation())->toBeTrue()
        ->and(ServiceResource::shouldRegisterNavigation())->toBeTrue()
        // No badge either: a nought beside an offer nobody has made is noise.
        ->and(SpaceResource::getNavigationBadge())->toBeNull()
        ->and(ServiceResource::getNavigationBadge())->toBeNull();
});

test('a badge counts what the organization already has', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->pro()->create();
    $organization->addMember($member);

    $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);
    $organization->people()->create(['name' => 'Popescu', 'role' => 'Antrenor principal']);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    expect(OrganizationSportResource::getNavigationBadge())->toBe('1')
        ->and(PersonResource::getNavigationBadge())->toBe('1')
        ->and(LocationResource::getNavigationBadge())->toBeNull();
});
