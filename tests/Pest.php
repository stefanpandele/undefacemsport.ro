<?php

use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\ScheduleSlot;
use App\Models\Space;
use App\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A club teaching one sport at the given location, with a training slot.
 *
 * Shared because both the location page and the sport page are about the same
 * three ways in, and they have to be set up identically to be comparable.
 */
function clubAt(Location $location, Sport $sport, string $name): Organization
{
    $club = Organization::factory()->create(['name' => $name]);

    $presence = OrganizationLocation::create([
        'organization_id' => $club->getKey(),
        'location_id' => $location->getKey(),
    ]);
    $presence->sports()->sync([$sport->getKey()]);

    ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Training,
        'organization_id' => $club->getKey(),
        'organization_location_sport_id' => $presence->organizationLocationSports->first()->getKey(),
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:30',
    ]);

    return $club;
}

/**
 * A space at the given location, open every day. Unmanaged when `$managed` is
 * false — a park court, with nobody behind it.
 */
function spaceAt(
    Location $location,
    ?Sport $sport,
    SpaceAccessMode $mode,
    ?float $price,
    bool $managed = true,
): Space {
    $presence = null;

    if ($managed) {
        $venue = Organization::factory()->venue()->create();
        $presence = OrganizationLocation::create([
            'organization_id' => $venue->getKey(),
            'location_id' => $location->getKey(),
        ]);
    }

    $space = Space::factory()->create([
        'location_id' => $location->getKey(),
        'organization_location_id' => $presence?->getKey(),
        'sport_id' => $sport?->getKey(),
        'access_mode' => $mode,
        'price' => $price,
        'price_unit' => $mode->defaultPriceUnit(),
    ]);

    foreach (Weekday::cases() as $day) {
        ScheduleSlot::create([
            'kind' => ScheduleSlotKind::Access,
            'organization_id' => $presence?->organization_id,
            'space_id' => $space->getKey(),
            'day_of_week' => $day,
            'start_time' => '07:00',
            'end_time' => '22:00',
        ]);
    }

    return $space;
}
