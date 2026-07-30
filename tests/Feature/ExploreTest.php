<?php

use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use Illuminate\Support\Carbon;

/**
 * A club teaching one sport at one location, in the given city.
 */
function trainingAt(string $city, string $locationName, Sport $sport, ?Organization $organization = null): Location
{
    $organization ??= Organization::factory()->create();

    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj',
        'city' => $city,
        'address' => 'Str. '.$locationName,
        'name' => $locationName,
    ], [$sport->id]);

    return $organizationLocation->location;
}

test('the explore page lists a city with its locations and sports', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet', 'icon' => '🏀', 'color' => '#E07A2F']);
    $location = trainingAt('Cluj-Napoca', 'Sala Polivalentă', $sport);
    $location->facilities()->attach(Facility::factory()->count(2)->create());

    $this->get('/explorare?oras=Cluj-Napoca')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/explore/Index')
            ->where('city', 'Cluj-Napoca')
            ->where('cities.0.name', 'Cluj-Napoca')
            ->has('locations', 1)
            ->where('locations.0.slug', $location->slug)
            ->where('locations.0.name', 'Sala Polivalentă')
            ->where('locations.0.clubCount', 1)
            ->where('locations.0.facilityCount', 2)
            ->where('locations.0.live', false)
            ->where('locations.0.color', '#E07A2F')
            ->where('locations.0.sports.0.key', 'baschet')
            ->has('sports', 1)
            ->where('sports.0.key', 'baschet')
            ->where('sports.0.icon', '🏀')
            ->where('sports.0.locationCount', 1)
            ->where('sports.0.clubCount', 1)
            ->has('facilities', 2)
        );
});

test('without a city the page offers a picker instead of guessing one', function () {
    $sport = Sport::factory()->create(['icon' => '🏀', 'color' => '#E07A2F']);
    trainingAt('Cluj-Napoca', 'Sala A', $sport);
    trainingAt('Cluj-Napoca', 'Sala B', $sport);
    trainingAt('Brașov', 'Sala C', $sport);

    // Picking for the visitor used to drop someone from Cluj into the busiest
    // city without saying so; now nothing is chosen and nothing is filtered.
    $this->get('/explorare')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('city', null)
            ->has('locations', 0)
            ->has('sports', 0)
            ->has('facilities', 0)
            // Busiest first, so the cities most people want need no reading.
            ->where('cities.0.name', 'Cluj-Napoca')
            ->where('cities.0.locationCount', 2)
            ->where('cities.0.clubCount', 2)
            ->where('cities.0.sportCount', 1)
            ->where('cities.0.color', '#E07A2F')
            ->where('cities.1.name', 'Brașov')
            ->where('cities.1.locationCount', 1)
        );
});

test('a city card counts the distinct sports played there', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);

    // Two sports, but three locations — the count is of sports, not of rows.
    trainingAt('Cluj-Napoca', 'Bazinul A', $swimming);
    trainingAt('Cluj-Napoca', 'Bazinul B', $swimming);
    trainingAt('Cluj-Napoca', 'Stadionul C', $football);

    $this->get('/explorare')
        ->assertInertia(fn ($page) => $page
            ->where('cities.0.sportCount', 2)
            ->where('cities.0.locationCount', 3)
        );
});

test('an unknown city falls back to the picker rather than to another city', function () {
    trainingAt('Cluj-Napoca', 'Sala A', Sport::factory()->create());

    $this->get('/explorare?oras=Atlantida')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('city', null)->has('locations', 0));
});

test('choosing a city shows only its locations', function () {
    $sport = Sport::factory()->create();
    trainingAt('Cluj-Napoca', 'Sala A', $sport);
    trainingAt('Brașov', 'Sala C', $sport);

    $this->get('/explorare?oras=Bra%C8%99ov')
        ->assertInertia(fn ($page) => $page
            ->where('city', 'Brașov')
            ->has('locations', 1)
            ->where('locations.0.name', 'Sala C')
        );
});

test('a city card carries a centre point, so a shared position can pick it', function () {
    $sport = Sport::factory()->create();
    $location = trainingAt('Cluj-Napoca', 'Sala A', $sport);
    $location->update(['latitude' => 46.77, 'longitude' => 23.59]);

    $this->get('/explorare')
        ->assertInertia(fn ($page) => $page
            ->where('cities.0.lat', 46.77)
            ->where('cities.0.lng', 23.59)
        );
});

test('the list can be filtered by sport, facility and name', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);

    $pool = trainingAt('Cluj-Napoca', 'Bazinul Universitar', $swimming);
    trainingAt('Cluj-Napoca', 'Stadionul Municipal', $football);

    $parking = Facility::factory()->create(['name' => 'Parcare']);
    $pool->facilities()->attach($parking);

    $this->get('/explorare?oras=Cluj-Napoca&sport=inot')
        ->assertInertia(fn ($page) => $page
            ->has('locations', 1)
            ->where('locations.0.name', 'Bazinul Universitar')
            ->where('filters.sport', 'inot')
        );

    $this->get('/explorare?oras=Cluj-Napoca&facilitati[]='.$parking->id)
        ->assertInertia(fn ($page) => $page->has('locations', 1)->where('locations.0.name', 'Bazinul Universitar'));

    $this->get('/explorare?oras=Cluj-Napoca&cauta=Stadion')
        ->assertInertia(fn ($page) => $page->has('locations', 1)->where('locations.0.name', 'Stadionul Municipal'));

    $this->get('/explorare?oras=Cluj-Napoca&cauta=nimic')
        ->assertInertia(fn ($page) => $page->has('locations', 0));
});

test('picking several amenities narrows the list to places that have them all', function () {
    $sport = Sport::factory()->create();

    $both = trainingAt('Cluj-Napoca', 'Sala Completă', $sport);
    $onlyParking = trainingAt('Cluj-Napoca', 'Sala cu Parcare', $sport);
    trainingAt('Cluj-Napoca', 'Sala Goală', $sport);

    $parking = Facility::factory()->create(['name' => 'Parcare', 'sort_order' => 0]);
    $showers = Facility::factory()->create(['name' => 'Dușuri', 'sort_order' => 1]);

    $both->facilities()->attach([$parking->id, $showers->id]);
    $onlyParking->facilities()->attach($parking);

    // One amenity: both places that have it.
    $this->get('/explorare?oras=Cluj-Napoca&facilitati[]='.$parking->id)
        ->assertInertia(fn ($page) => $page
            ->has('locations', 2)
            ->where('filters.facilities', [$parking->id])
        );

    // Two amenities: only the place that has both, not either.
    $this->get("/explorare?oras=Cluj-Napoca&facilitati[]={$parking->id}&facilitati[]={$showers->id}")
        ->assertInertia(fn ($page) => $page
            ->has('locations', 1)
            ->where('locations.0.name', 'Sala Completă')
            ->where('filters.facilities', [$parking->id, $showers->id])
        );
});

test('the amenity filters follow their curated sort order', function () {
    $sport = Sport::factory()->create();
    $location = trainingAt('Cluj-Napoca', 'Sala A', $sport);

    // Alphabetically this is Dușuri, Parcare, Vestiare — the curated order wins.
    $location->facilities()->attach([
        Facility::factory()->create(['name' => 'Vestiare', 'icon' => '🚿', 'sort_order' => 1])->id,
        Facility::factory()->create(['name' => 'Parcare', 'icon' => '🅿️', 'sort_order' => 0])->id,
        Facility::factory()->create(['name' => 'Dușuri', 'icon' => '🚻', 'sort_order' => 2])->id,
    ]);

    $this->get('/explorare?oras=Cluj-Napoca')
        ->assertInertia(fn ($page) => $page
            ->where('facilities.0.name', 'Parcare')
            ->where('facilities.0.icon', '🅿️')
            ->where('facilities.1.name', 'Vestiare')
            ->where('facilities.2.name', 'Dușuri')
        );
});

test('a location training right now is marked as live', function () {
    $now = Carbon::create(2026, 7, 27, 18, 30); // a Monday
    Carbon::setTestNow($now);

    $sport = Sport::factory()->create();
    $organization = Organization::factory()->create();
    $location = trainingAt('Cluj-Napoca', 'Sala Live', $sport, $organization);

    $organizationLocationSport = OrganizationLocationSport::query()->firstOrFail();

    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $organizationLocationSport->id,
        'day_of_week' => Weekday::fromDate($now),
        'start_time' => '18:00',
        'end_time' => '19:00',
        'age_group_id' => AgeGroup::factory()->create()->id,
    ]);

    $this->get('/explorare?oras=Cluj-Napoca')
        ->assertInertia(fn ($page) => $page->where('locations.0.live', true));

    // An hour later the session is over.
    Carbon::setTestNow($now->copy()->addHours(2));

    $this->get('/explorare?oras=Cluj-Napoca')
        ->assertInertia(fn ($page) => $page->where('locations.0.live', false));

    Carbon::setTestNow();

    expect($location->slug)->toBe('sala-live');
});

test('a sport card carries the age groups offered for it in that city', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $organization = Organization::factory()->create();
    trainingAt('Cluj-Napoca', 'Bazinul Mare', $sport, $organization);

    $organizationSport = $organization->organizationSports()->create(['sport_id' => $sport->id]);
    $organizationSport->ageGroups()->attach(AgeGroup::factory()->create(['name' => '3–7 ani', 'sort_order' => 1]));

    $this->get('/explorare?oras=Cluj-Napoca')
        ->assertInertia(fn ($page) => $page->where('sports.0.ages', ['3–7 ani']));
});

test('the explore page works with no data at all', function () {
    $this->get('/explorare')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('city', null)
            ->has('locations', 0)
            ->has('sports', 0)
        );
});
