<?php

use App\Enums\SpaceAccessMode;
use App\Models\Level;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Sport;

/**
 * A club at the location teaching one sport at the given levels.
 *
 * @param  list<Level>  $levels
 */
function clubTeaching(Location $location, Sport $sport, string $name, array $levels): Organization
{
    $club = clubAt($location, $sport, $name);

    $club->organizationSports()
        ->firstOrCreate(['sport_id' => $sport->getKey()], ['sort_order' => 0])
        ->levels()
        ->sync(collect($levels)->map->getKey()->all());

    return $club;
}

test('only the levels actually taught in the city are offered', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $competition = Level::create(['name' => 'Performanță', 'sort_order' => 4]);
    Level::create(['name' => 'Amatori', 'sort_order' => 2]);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubTeaching($location, $sport, 'CS Delfinul', [$beginner, $competition]);

    $this->get(route('sports.show', ['slug' => 'inot', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page
            // "Amatori" is taught by nobody here, and offering it would be a
            // dead end dressed up as a choice.
            ->has('levels', 2)
            ->where('levels.0.name', 'Inițiere')
            ->where('levels.0.slug', 'initiere')
            ->where('levels.1.name', 'Performanță')
            ->where('filters.level', null),
    );
});

test('choosing a level keeps only the clubs that teach it', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $competition = Level::create(['name' => 'Performanță', 'sort_order' => 4]);

    $beginners = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Bazinul mic']);
    clubTeaching($beginners, $sport, 'CS Initiere', [$beginner]);

    $serious = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Bazinul olimpic']);
    clubTeaching($serious, $sport, 'CS Performanta', [$competition]);

    $names = fn (?string $level): array => collect(
        $this->get(route('sports.show', array_filter([
            'slug' => 'inot',
            'city' => 'cluj-napoca',
            'nivel' => $level,
        ])))->viewData('page')['props']['ways'],
    )->flatMap(fn (array $way): array => $way['locations'])->pluck('name')->all();

    expect($names('initiere'))->toBe(['Bazinul mic'])
        ->and($names('performanta'))->toBe(['Bazinul olimpic'])
        ->and($names(null))->toBe(['Bazinul mic', 'Bazinul olimpic']);
});

test('a level drops the ways that have no level at all', function () {
    // A rentable court is not beginner or advanced. Listing one under a level
    // filter would answer a question nobody asked.
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);

    $withClub = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubTeaching($withClub, $sport, 'CS Test', [$beginner]);

    $rental = Location::factory()->create(['city' => 'Cluj-Napoca']);
    spaceAt($rental, $sport, SpaceAccessMode::ExclusiveRental, 120);

    $this->get(route('sports.show', ['slug' => 'baschet', 'city' => 'cluj-napoca']))
        ->assertInertia(fn ($page) => $page->has('ways', 2));

    $this->get(route('sports.show', ['slug' => 'baschet', 'city' => 'cluj-napoca', 'nivel' => 'initiere']))
        ->assertInertia(
            fn ($page) => $page->has('ways', 1)->where('ways.0.key', 'cursuri'),
        );
});

test('an unknown level is ignored rather than emptying the page', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubTeaching($location, $sport, 'CS Test', [$beginner]);

    $this->get(route('sports.show', ['slug' => 'inot', 'city' => 'cluj-napoca', 'nivel' => 'inventat']))
        ->assertInertia(
            fn ($page) => $page->where('filters.level', null)->has('ways', 1),
        );
});

test('the level filter is absent before a city is chosen', function () {
    // Levels are per city: which ones exist depends on who teaches there.
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubTeaching($location, $sport, 'CS Test', [$beginner]);

    $this->get(route('sports.show', 'inot'))->assertInertia(
        fn ($page) => $page->where('levels', [])->where('filters.level', null),
    );
});
