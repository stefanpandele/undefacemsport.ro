<?php

use App\Models\Club;
use App\Models\Sport;

/**
 * A club teaching one sport at one location in the given city.
 */
function sportTaughtAt(string $city, string $locationName, Sport $sport, ?Club $club = null): void
{
    ($club ?? Club::factory()->create())->syncLocation([
        'county' => 'Cluj',
        'city' => $city,
        'address' => 'Str. '.$locationName,
        'name' => $locationName,
    ], [$sport->id]);
}

test('the sports page lists what is taught, biggest first', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot', 'icon' => '🏊', 'color' => '#1D7FB8']);
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal', 'icon' => '⚽']);
    Sport::factory()->create(['slug' => 'polo', 'name' => 'Polo']); // taught nowhere

    $club = Club::factory()->create();
    sportTaughtAt('Cluj-Napoca', 'Bazinul A', $swimming, $club);
    sportTaughtAt('Cluj-Napoca', 'Bazinul B', $swimming, $club);
    sportTaughtAt('Brașov', 'Bazinul C', $swimming);
    sportTaughtAt('Cluj-Napoca', 'Stadionul D', $football);

    $this->get('/sporturi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/sports/Index')
            // Only sports someone actually teaches — an empty one is a dead end.
            ->has('sports', 2)
            ->where('sports.0.key', 'inot')
            ->where('sports.0.icon', '🏊')
            ->where('sports.0.color', '#1D7FB8')
            ->where('sports.0.locationCount', 3)
            ->where('sports.0.clubCount', 2)
            ->where('sports.0.cityCount', 2)
            ->where('sports.1.key', 'fotbal')
            ->where('sports.1.locationCount', 1)
        );
});

test('the county field narrows both the sports and their counts', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);

    sportTaughtAt('Cluj-Napoca', 'Bazinul A', $swimming);
    sportTaughtAt('Cluj-Napoca', 'Stadionul B', $football);

    // A second county, so the unfiltered counts are visibly larger.
    Club::factory()->create()->syncLocation([
        'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. C', 'name' => 'Bazinul C',
    ], [$swimming->id]);

    $this->get('/sporturi')
        ->assertInertia(fn ($page) => $page
            ->has('sports', 2)
            ->where('sports.0.locationCount', 2)
            // Only counties that actually hold a location are offered.
            ->where('counties', ['Brașov', 'Cluj'])
            ->where('filters.county', null)
        );

    $this->get('/sporturi?judet=Bra%C8%99ov')
        ->assertInertia(fn ($page) => $page
            ->has('sports', 1)
            ->where('sports.0.key', 'inot')
            ->where('sports.0.locationCount', 1)
            ->where('filters.county', 'Brașov')
        );

    // A county nobody trains in is ignored rather than showing an empty page.
    $this->get('/sporturi?judet=Atlantida')
        ->assertInertia(fn ($page) => $page->where('filters.county', null)->has('sports', 2));
});

test('the homepage shows the seven biggest sports', function () {
    foreach (range(1, 9) as $index) {
        $sport = Sport::factory()->create(['slug' => 'sport-'.$index, 'name' => 'Sport '.$index]);

        // Each sport gets one more location than the last, so the order is known.
        foreach (range(1, $index) as $location) {
            sportTaughtAt('Cluj-Napoca', "Sala {$index}-{$location}", $sport);
        }
    }

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('popularSports', 7)
            ->where('popularSports.0.key', 'sport-9')
            ->where('popularSports.6.key', 'sport-3')
        );
});

test('the homepage copes with no sports at all', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('popularSports', 0)
            ->has('sports', 0)
            ->has('cities', 0)
            ->where('stats.locations', 0)
        );
});

test('the homepage headline numbers are counted, not claimed', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    Sport::factory()->create(['slug' => 'polo', 'name' => 'Polo']); // taught nowhere

    $club = Club::factory()->create();
    sportTaughtAt('Cluj-Napoca', 'Bazinul A', $swimming, $club);
    sportTaughtAt('Cluj-Napoca', 'Stadionul B', $football, $club);
    sportTaughtAt('Brașov', 'Sala C', $swimming);

    // A club with no location at all is not an active club.
    Club::factory()->create();

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('stats.locations', 3)
            ->where('stats.clubs', 2)
            ->where('stats.cities', 2)
            ->where('stats.sports', 2)
            // The hero search must not offer a sport that leads nowhere.
            ->has('sports', 2)
            ->has('cities', 2)
            ->where('cities.0.name', 'Brașov')
        );
});

test('picking a sport narrows the city picker to cities that have it', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);

    sportTaughtAt('Cluj-Napoca', 'Bazinul A', $swimming);
    sportTaughtAt('Brașov', 'Stadionul B', $football);

    // Offering a city with none of the chosen sport would be a dead end
    // dressed up as a choice.
    $this->get('/explorare?sport=inot')
        ->assertInertia(fn ($page) => $page
            ->where('city', null)
            ->where('filters.sport', 'inot')
            ->has('cities', 1)
            ->where('cities.0.name', 'Cluj-Napoca')
        );

    $this->get('/explorare')
        ->assertInertia(fn ($page) => $page->has('cities', 2));
});
