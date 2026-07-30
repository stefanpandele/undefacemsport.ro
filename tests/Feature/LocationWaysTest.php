<?php

use App\Enums\FacilityStatus;
use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Location;
use App\Models\ScheduleSlot;
use App\Models\Sport;

test('a pool with a club and a venue offers both ways in', function () {
    $location = Location::factory()->create(['slug' => 'bazinul-test']);
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    clubAt($location, $sport, 'CS Delfinul');
    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 45);

    $this->get(route('locations.show', 'bazinul-test'))->assertInertia(
        fn ($page) => $page
            ->has('location.ways.inot', 2)
            ->where('location.ways.inot.0.key', 'organizat')
            ->where('location.ways.inot.0.who', 'un club')
            ->where('location.ways.inot.1.key', 'liber')
            ->where('location.ways.inot.1.price', '45 lei / intrare'),
    );
});

test('a place with only one way in offers no choice at all', function () {
    $location = Location::factory()->create(['slug' => 'doar-club']);
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    clubAt($location, $sport, 'CS Delfinul');

    $this->get(route('locations.show', 'doar-club'))->assertInertia(
        fn ($page) => $page
            ->has('location.ways.inot', 1)
            ->where('location.ways.inot.0.key', 'organizat'),
    );
});

test('a park court is a free way in with nobody behind it', function () {
    $location = Location::factory()->create(['slug' => 'parcul-test']);
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $this->get(route('locations.show', 'parcul-test'))->assertInertia(
        fn ($page) => $page
            ->has('location.ways.baschet', 1)
            ->where('location.ways.baschet.0.key', 'liber')
            ->where('location.ways.baschet.0.price', 'Gratuit')
            ->where('location.ways.baschet.0.who', 'Spațiu public, neadministrat')
            ->where('location.ways.baschet.0.spaces.0.unmanaged', true)
            ->where('location.ways.baschet.0.spaces.0.operator', null),
    );
});

test('a club card carries nothing about the venue programme', function () {
    // The separation rule, as a regression: a club must never be credited with
    // somebody else's opening hours.
    $location = Location::factory()->create(['slug' => 'mixt']);
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    clubAt($location, $sport, 'CS Delfinul');
    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 45);

    $this->get(route('locations.show', 'mixt'))->assertInertia(function ($page) {
        $club = collect($page->toArray()['props']['location']['clubs'])->firstWhere('name', 'CS Delfinul');

        expect(json_encode($club))
            ->not->toContain('Acces liber')
            ->not->toContain('45 lei');

        return $page;
    });
});

test('the day view belongs to the location, not to a sport section', function () {
    $location = Location::factory()->create(['slug' => 'cu-zi']);
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 45);

    $this->get(route('locations.show', 'cu-zi'))->assertInertia(
        fn ($page) => $page
            ->has('location.day')
            ->has('location.day.rows', 1)
            ->where('location.day.rows.0.bars.0.start', 7),
    );
});

test('a location with no spaces has no day view to show', function () {
    $location = Location::factory()->create(['slug' => 'fara-spatii']);
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    clubAt($location, $sport, 'CS Delfinul');

    $this->get(route('locations.show', 'fara-spatii'))->assertInertia(
        fn ($page) => $page->where('location.day', null),
    );
});

test('a space awaiting review stays off the page', function () {
    $location = Location::factory()->create(['slug' => 'in-asteptare']);
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 0, managed: false)
        ->forceFill(['status' => FacilityStatus::Pending])->save();

    $this->get(route('locations.show', 'in-asteptare'))->assertInertia(
        fn ($page) => $page->where('location.ways', [])->where('location.day', null),
    );
});

test('a space with no sport is not a way to play one', function () {
    // A sauna is a thing at the place, not a column on the sport chooser.
    $location = Location::factory()->create(['slug' => 'cu-sauna']);

    spaceAt($location, null, SpaceAccessMode::OpenAccess, 50);

    $this->get(route('locations.show', 'cu-sauna'))->assertInertia(
        fn ($page) => $page->where('location.ways', []),
    );
});

test('a hall with two ways in offers both from one record', function () {
    $location = Location::factory()->create(['slug' => 'sala-mixta']);
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

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

    $this->get(route('locations.show', 'sala-mixta'))->assertInertia(
        fn ($page) => $page
            ->has('location.ways.baschet', 2)
            ->where('location.ways.baschet.0.key', 'liber')
            ->where('location.ways.baschet.0.price', '25 lei / intrare')
            ->where('location.ways.baschet.1.key', 'inchiriere')
            ->where('location.ways.baschet.1.price', '180 lei / oră'),
    );
});

/*
|--------------------------------------------------------------------------
| The explore facet
|--------------------------------------------------------------------------
*/

test('the explore page can be narrowed to one way in', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

    $withClub = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Sala cu club']);
    clubAt($withClub, $sport, 'CS Test');

    $withPark = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Parcul liber']);
    spaceAt($withPark, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $withRental = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Baza de inchiriat']);
    spaceAt($withRental, $sport, SpaceAccessMode::ExclusiveRental, 120);

    $names = fn (string $mod): array => collect(
        $this->get(route('explore', ['oras' => 'Cluj-Napoca', 'mod' => $mod]))
            ->viewData('page')['props']['locations'],
    )->pluck('name')->all();

    expect($names('liber'))->toBe(['Parcul liber'])
        ->and($names('inchiriere'))->toBe(['Baza de inchiriat'])
        ->and($names('organizat'))->toBe(['Sala cu club']);
});

test('an unknown way in is ignored rather than emptying the page', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $sport, 'CS Test');

    $this->get(route('explore', ['oras' => 'Cluj-Napoca', 'mod' => 'ceva-inventat']))
        ->assertInertia(
            fn ($page) => $page
                ->where('filters.way', null)
                ->has('locations', 1),
        );
});

test('the explore page offers the three ways as filters', function () {
    $this->get(route('explore'))->assertInertia(
        fn ($page) => $page
            ->has('ways', 3)
            ->where('ways.0.value', 'organizat')
            ->where('ways.1.value', 'liber')
            ->where('ways.2.value', 'inchiriere'),
    );
});
