<?php

use App\Enums\FacilityStatus;
use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Location;
use App\Models\ScheduleSlot;
use App\Models\Sport;

test('the sport page asks for a city before answering', function () {
    // Guessing one used to land somebody from Cluj in București without saying
    // so, which is worse than asking.
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $cluj = Location::factory()->create(['city' => 'Cluj-Napoca']);
    $bucuresti = Location::factory()->create(['city' => 'București']);

    clubAt($cluj, $sport, 'CS Cluj');
    clubAt($bucuresti, $sport, 'CS Bucuresti');
    spaceAt($bucuresti, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $this->get(route('sports.show', 'baschet'))->assertInertia(
        fn ($page) => $page
            ->component('public/sports/Show')
            ->where('city', null)
            ->where('ways', [])
            ->has('cities', 2)
            // Busiest first: București has two ways in, Cluj one.
            ->where('cities.0.name', 'București')
            ->where('cities.0.slug', 'bucuresti')
            ->where('cities.1.name', 'Cluj-Napoca'),
    );
});

test('a city is reached by its slug, and the URL stays readable', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $sport, 'CS Cluj');

    $this->get('/sporturi/baschet/cluj-napoca')->assertInertia(
        fn ($page) => $page->where('city', 'Cluj-Napoca'),
    );
});

test('the page groups the places by how you get in', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

    $withClub = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Sala cu club']);
    clubAt($withClub, $sport, 'CS Test');

    $park = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Parcul central']);
    spaceAt($park, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $rental = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Baza privata']);
    spaceAt($rental, $sport, SpaceAccessMode::ExclusiveRental, 180);

    $this->get(route('sports.show', ['slug' => 'baschet', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page
            ->has('ways', 3)
            ->where('ways.0.key', 'cursuri')
            ->where('ways.0.locations.0.name', 'Sala cu club')
            ->where('ways.0.locations.0.who', 'un club')
            // A club publishes no price, and inventing one would be worse.
            ->where('ways.0.locations.0.price', null)
            ->where('ways.1.key', 'agrement')
            ->where('ways.1.locations.0.name', 'Parcul central')
            ->where('ways.1.locations.0.price', 'Gratuit')
            ->where('ways.1.locations.0.who', 'Spațiu public')
            ->where('ways.2.key', 'inchiriere')
            ->where('ways.2.locations.0.price', '180 lei / oră'),
    );
});

test('a way nobody offers here is absent, not shown empty', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $sport, 'CS Test');

    $this->get(route('sports.show', ['slug' => 'baschet', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page->has('ways', 1)->where('ways.0.key', 'cursuri'),
    );
});

test('a hall with two ways in appears under both', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Sala mixta']);

    $hall = spaceAt($location, $sport, SpaceAccessMode::ExclusiveRental, 180);
    ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Access,
        'space_id' => $hall->getKey(),
        'day_of_week' => Weekday::Friday,
        'start_time' => '20:00',
        'end_time' => '22:00',
        'price' => 25,
        'access_mode' => SpaceAccessMode::OpenAccess,
        'price_unit' => PriceUnit::Entry,
    ]);

    $this->get(route('sports.show', ['slug' => 'baschet', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page
            ->has('ways', 2)
            ->where('ways.0.locations.0.name', 'Sala mixta')
            ->where('ways.0.locations.0.price', '25 lei / intrare')
            ->where('ways.1.locations.0.price', '180 lei / oră'),
    );
});

test('an unknown city falls back to the picker instead of a dead end', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $sport, 'CS Test');

    $this->get('/sporturi/baschet/timisoara')->assertInertia(
        fn ($page) => $page->where('city', null)->has('cities', 1),
    );
});

test('an unknown sport is a 404, not an empty page', function () {
    $this->get('/sporturi/curling-pe-marte')->assertNotFound();
});

test('a sport nobody offers still has a page, and says so', function () {
    Sport::factory()->create(['slug' => 'sah', 'name' => 'Șah']);

    $this->get(route('sports.show', 'sah'))->assertInertia(
        fn ($page) => $page->where('cities', [])->where('ways', []),
    );
});

test('a space awaiting review keeps its city off the page', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);

    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 0, managed: false)
        ->forceFill(['status' => FacilityStatus::Pending])->save();

    $this->get(route('sports.show', 'baschet'))->assertInertia(
        fn ($page) => $page->where('cities', []),
    );
});

test('the city cards say which ways in each city has', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);

    clubAt($location, $sport, 'CS Test');
    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $this->get(route('sports.show', 'baschet'))->assertInertia(
        fn ($page) => $page
            ->where('cities.0.ways.cursuri', 1)
            ->where('cities.0.ways.agrement', 1)
            // Absent rather than zero: a way nobody offers is not a fact about
            // the city, it is the absence of one.
            ->missing('cities.0.ways.inchiriere'),
    );
});

test('the sports index links straight to each sport page', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $sport, 'CS Test');

    $this->get(route('sports.index'))->assertInertia(
        fn ($page) => $page->where('sports.0.key', 'baschet'),
    );

    // The route the card points at has to exist and resolve.
    $this->get(route('sports.show', 'baschet'))->assertSuccessful();
});
