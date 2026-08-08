<?php

use App\Enums\SpaceAccessMode;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\Service;
use App\Models\Space;
use App\Models\Specialty;
use App\Models\Sport;

/**
 * An organization present at a location, with one service on offer.
 */
function offering(Location $location, Organization $organization, string $name, ?int $locationId = null): Service
{
    $presence = OrganizationLocation::firstOrCreate([
        'organization_id' => $organization->getKey(),
        'location_id' => $location->getKey(),
    ]);

    return Service::factory()->create([
        'organization_id' => $organization->getKey(),
        'organization_location_id' => $locationId === null ? null : $presence->getKey(),
        'name' => $name,
        'price' => 150,
        'duration_minutes' => 50,
    ]);
}

/*
|--------------------------------------------------------------------------
| A sport-less space finally has a home
|--------------------------------------------------------------------------
*/

test('a sauna appears as something you can pay for, without being a sport', function () {
    // It used to be dropped from the page entirely: correctly not a sport, but
    // with nowhere to live either.
    $location = Location::factory()->create(['slug' => 'cu-sauna']);
    spaceAt($location, null, SpaceAccessMode::OpenAccess, 50);

    $this->get(route('locations.show', 'cu-sauna'))->assertInertia(
        fn ($page) => $page
            ->has('location.extras', 1)
            ->where('location.extras.0.detail', '50 lei / intrare')
            // And still not a sport: the chooser has nothing in it.
            ->where('location.ways', []),
    );
});

test('a space with a sport stays where it belongs, not among the extras', function () {
    $location = Location::factory()->create(['slug' => 'cu-bazin']);
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 45);

    $this->get(route('locations.show', 'cu-bazin'))->assertInertia(
        fn ($page) => $page
            ->where('location.extras', [])
            ->has('location.ways.inot', 1),
    );
});

test('a free sauna says so rather than saying nothing', function () {
    $location = Location::factory()->create(['slug' => 'sauna-gratis']);
    spaceAt($location, null, SpaceAccessMode::OpenAccess, 0, managed: false);

    $this->get(route('locations.show', 'sauna-gratis'))->assertInertia(
        fn ($page) => $page->where('location.extras.0.detail', 'Gratuit'),
    );
});

/*
|--------------------------------------------------------------------------
| Services beside the main activity
|--------------------------------------------------------------------------
*/

test('a studio massage shows up at the place, priced and timed', function () {
    // The pilates case: you come for the classes, and while you are there you can
    // pay for a massage.
    $location = Location::factory()->create(['slug' => 'studio-pilates']);
    $studio = Organization::factory()->create(['name' => 'Pilates Studio']);

    offering($location, $studio, 'Masaj terapeutic');

    $this->get(route('locations.show', 'studio-pilates'))->assertInertia(
        fn ($page) => $page
            ->has('location.extras', 1)
            ->where('location.extras.0.name', 'Masaj terapeutic')
            ->where('location.extras.0.detail', '150 lei')
            ->where('location.extras.0.meta', '50 min')
            ->where('location.extras.0.by', 'Pilates Studio'),
    );
});

test('a hotel lists its sauna and its massage under one heading', function () {
    // Two models behind one section, because the visitor does not care whether
    // they are buying a space or somebody's time.
    $location = Location::factory()->create(['slug' => 'hotel-test']);
    $hotel = Organization::factory()->venue()->create(['name' => 'Hotel Test']);

    OrganizationLocation::create([
        'organization_id' => $hotel->getKey(),
        'location_id' => $location->getKey(),
    ]);
    Space::factory()->unmanaged()->create([
        'location_id' => $location->getKey(),
        'name' => 'Saună',
        'sport_id' => null,
        'price' => 50,
    ]);
    Service::factory()->create([
        'organization_id' => $hotel->getKey(),
        'name' => 'Masaj',
        'price' => 150,
    ]);

    $this->get(route('locations.show', 'hotel-test'))->assertInertia(
        fn ($page) => $page->has('location.extras', 2),
    );
});

test('a service pinned to another branch is not on offer here', function () {
    $here = Location::factory()->create(['slug' => 'aici']);
    $elsewhere = Location::factory()->create();
    $clinic = Organization::factory()->practice()->create();

    $herePresence = OrganizationLocation::create([
        'organization_id' => $clinic->getKey(),
        'location_id' => $here->getKey(),
    ]);
    $otherPresence = OrganizationLocation::create([
        'organization_id' => $clinic->getKey(),
        'location_id' => $elsewhere->getKey(),
    ]);

    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'organization_location_id' => $otherPresence->getKey(),
        'name' => 'Doar la cealaltă filială',
    ]);
    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'organization_location_id' => $herePresence->getKey(),
        'name' => 'Aici',
    ]);
    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'organization_location_id' => null,
        'name' => 'La toate filialele',
    ]);

    $this->get(route('locations.show', 'aici'))->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['location']['extras'])->pluck('name');

        expect($names)->toContain('Aici', 'La toate filialele')
            ->and($names)->not->toContain('Doar la cealaltă filială');

        return $page;
    });
});

test('the availableAt scope agrees with what the page shows', function () {
    $location = Location::factory()->create();
    $clinic = Organization::factory()->practice()->create();
    $presence = OrganizationLocation::create([
        'organization_id' => $clinic->getKey(),
        'location_id' => $location->getKey(),
    ]);

    Service::factory()->create(['organization_id' => $clinic->getKey(), 'organization_location_id' => null]);
    Service::factory()->create(['organization_id' => $clinic->getKey(), 'organization_location_id' => $presence->getKey()]);
    // Somebody else's service at the same place.
    Service::factory()->create();

    expect(Service::query()->availableAt($presence)->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| The organization page
|--------------------------------------------------------------------------
*/

test('the organization page sells its services in their own tab', function () {
    $studio = Organization::factory()->create(['slug' => 'pilates-studio']);
    $specialty = Specialty::factory()->create(['name' => 'Masaj sportiv', 'slug' => 'masaj-sportiv']);

    Service::factory()->create([
        'organization_id' => $studio->getKey(),
        'specialty_id' => $specialty->getKey(),
        'name' => 'Masaj terapeutic',
        'price' => 120,
        'duration_minutes' => 30,
    ]);

    $this->get(route('organizations.show', 'pilates-studio'))->assertInertia(
        fn ($page) => $page
            ->has('organization.services', 1)
            ->where('organization.services.0.name', 'Masaj terapeutic')
            ->where('organization.services.0.price', '120 lei')
            ->where('organization.services.0.duration', '30 min')
            // A studio with nothing but services gets exactly one tab, and it is
            // the one its offer earned.
            ->has('organization.tabs', 1)
            ->where('organization.tabs.0.key', 'servicii'),
    );
});

test('the extras never reach discovery', function () {
    // Somebody searching for a masseur wants a practice, not a pilates studio
    // that happens to offer one. An extra earns a place on the page and nowhere
    // else.
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $studio = clubAt($location, $sport, 'CS Test');

    Service::factory()->create(['organization_id' => $studio->getKey(), 'name' => 'Masaj']);
    Space::factory()->unmanaged()->create([
        'location_id' => $location->getKey(),
        'name' => 'Saună',
        'sport_id' => null,
    ]);

    // Neither shows up as a way into a sport, in either surface.
    $this->get(route('explore', ['oras' => 'Cluj-Napoca']))->assertInertia(
        fn ($page) => $page->where('sports.0.key', 'baschet')->has('sports', 1),
    );

    $this->get(route('sports.show', ['slug' => 'baschet', 'city' => 'cluj-napoca']))
        ->assertInertia(fn ($page) => $page->has('ways', 1)->where('ways.0.key', 'organizat'));
});

/*
|--------------------------------------------------------------------------
| The guessed classification is gone
|--------------------------------------------------------------------------
*/

test('a benefit worded like a service is no longer guessed into a group', function () {
    // It used to be sorted by keyword out of a free-text label, which had no price,
    // no duration, and broke on the first wording nobody anticipated.
    $club = Organization::factory()->create(['slug' => 'clubul-x']);
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    $organizationSport = $club->organizationSports()->create([
        'sport_id' => $sport->getKey(),
        'sort_order' => 0,
    ]);
    $organizationSport->benefits()->create([
        'icon' => '💆',
        'label' => 'Masaj terapeutic după antrenament',
        'sort_order' => 0,
    ]);

    $this->get(route('organizations.show', 'clubul-x'))->assertInertia(function ($page) {
        $detail = $page->toArray()['props']['organization']['courses'][0];

        expect($detail)->not->toHaveKey('beyondSport')
            // It stays a trust chip, which is what a benefit is.
            ->and($detail['trustChips'])->toContain('💆 Masaj terapeutic după antrenament');

        return $page;
    });
});

test('the seeded showcase studio sells its massage instead of advertising it', function () {
    $this->seed();

    $studio = Organization::query()->where('slug', 'clubul-demo')->firstOrFail();
    $massage = $studio->services()->firstWhere('name', 'Masaj terapeutic după antrenament');

    expect($massage)->not->toBeNull()
        ->and($massage->priceLabel())->toBe('120 lei')
        ->and($massage->durationLabel())->toBe('30 min')
        // And it is no longer a chip pretending to be an offer.
        ->and($studio->organizationSports->flatMap->benefits->pluck('label'))
        ->not->toContain('Masaj terapeutic după antrenament');
});
