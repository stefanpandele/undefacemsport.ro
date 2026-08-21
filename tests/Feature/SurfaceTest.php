<?php

use App\Enums\SpaceAccessMode;
use App\Models\Location;
use App\Models\Space;
use App\Models\Sport;
use App\Models\Surface;
use Database\Seeders\SportSeeder;
use Database\Seeders\SurfaceSeeder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| The taxonomy
|--------------------------------------------------------------------------
*/

test('a surface belongs to the sports it is plausible for', function () {
    $clay = Surface::factory()->create(['name' => 'Zgură']);
    $tennis = Sport::factory()->create(['name' => 'Tenis']);
    $swimming = Sport::factory()->create(['name' => 'Înot']);

    $clay->sports()->attach($tennis);

    expect($tennis->surfaces->pluck('name')->all())->toBe(['Zgură'])
        ->and($swimming->surfaces)->toBeEmpty();
});

test('a space names its surface instead of describing it', function () {
    // Free text could not be filtered: "gazon sintetic", "sintetic" and "Gazon
    // Sintetic" were three answers to one question.
    $surface = Surface::factory()->create(['name' => 'Hard']);
    $space = Space::factory()->create(['surface_id' => $surface->getKey()]);

    expect($space->surface->name)->toBe('Hard')
        ->and($surface->spaces()->count())->toBe(1);
});

test('a surface that goes away leaves the space standing', function () {
    // The court is still there when somebody tidies the vocabulary.
    $surface = Surface::factory()->create();
    $space = Space::factory()->create(['surface_id' => $surface->getKey()]);

    $surface->delete();

    expect($space->refresh()->surface)->toBeNull()
        ->and(Space::query()->whereKey($space->getKey())->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The seeded vocabulary
|--------------------------------------------------------------------------
*/

test('seeding gives tennis its three surfaces and swimming none', function () {
    $this->seed([SportSeeder::class, SurfaceSeeder::class]);

    $tennis = Sport::query()->where('name', 'Tenis')->first();
    $swimming = Sport::query()->where('name', 'Înot')->first();

    expect($tennis)->not->toBeNull()
        ->and($tennis->surfaces->pluck('name')->all())->toBe(['Zgură', 'Iarbă', 'Hard'])
        // A pool has no surface worth asking about, and the field must not appear.
        ->and($swimming->surfaces)->toBeEmpty();
});

test('the surface seeder is idempotent', function () {
    $this->seed([SportSeeder::class, SurfaceSeeder::class]);
    $surfaces = Surface::count();
    $links = DB::table('sport_surface')->count();

    (new SurfaceSeeder)->run();

    expect(Surface::count())->toBe($surfaces)
        ->and(DB::table('sport_surface')->count())->toBe($links);
});

/*
|--------------------------------------------------------------------------
| The form
|--------------------------------------------------------------------------
*/

test('the surface field offers only what the chosen sport is played on', function () {
    $this->seed([SportSeeder::class, SurfaceSeeder::class]);

    $tennis = Sport::query()->where('name', 'Tenis')->firstOrFail();
    $swimming = Sport::query()->where('name', 'Înot')->firstOrFail();

    expect(array_values(Surface::optionsFor($tennis->getKey())))
        ->toBe(['Zgură', 'Iarbă', 'Hard'])
        ->and(Surface::optionsFor($swimming->getKey()))->toBe([])
        // A sauna has no sport, so there is nothing to ask.
        ->and(Surface::optionsFor(null))->toBe([]);
});

test('a sport with one possible surface is not asked the question', function () {
    // A squash court is parquet, and so is every other one. Offering a select
    // with a single option asks for a decision that has already been made — the
    // same rule the access-mode chooser follows on the public pages.
    $this->seed([SportSeeder::class, SurfaceSeeder::class]);

    $squash = Sport::query()->where('name', 'Squash')->firstOrFail();

    expect($squash->surfaces->pluck('name')->all())->toBe(['Parchet'])
        ->and(Surface::optionsFor($squash->getKey()))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Discovery
|--------------------------------------------------------------------------
*/

test('the sport page narrows a way in to one surface', function () {
    // The whole reason this is a taxonomy and not a string.
    $this->seed([SportSeeder::class, SurfaceSeeder::class]);

    $tennis = Sport::query()->where('name', 'Tenis')->firstOrFail();
    $clay = Surface::query()->where('name', 'Zgură')->firstOrFail();
    $hard = Surface::query()->where('name', 'Hard')->firstOrFail();

    $claySite = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Baza Zgură']);
    $hardSite = Location::factory()->create(['city' => 'Cluj-Napoca', 'name' => 'Baza Hard']);

    spaceAt($claySite, $tennis, SpaceAccessMode::ExclusiveRental, 80)
        ->forceFill(['surface_id' => $clay->getKey()])->save();
    spaceAt($hardSite, $tennis, SpaceAccessMode::ExclusiveRental, 90)
        ->forceFill(['surface_id' => $hard->getKey()])->save();

    $url = route('sports.show', ['slug' => $tennis->slug, 'city' => 'cluj-napoca']);

    $this->get($url)->assertInertia(fn ($page) => $page
        ->has('surfaces', 2)
        ->has('ways.0.locations', 2));

    $this->get($url.'?suprafata=zgura')->assertInertia(fn ($page) => $page
        ->where('filters.surface', 'zgura')
        ->has('ways.0.locations', 1)
        ->where('ways.0.locations.0.name', 'Baza Zgură'));
});

test('a sport nobody asks the question about offers no surface filter', function () {
    $this->seed([SportSeeder::class, SurfaceSeeder::class]);

    $swimming = Sport::query()->where('name', 'Înot')->firstOrFail();
    $pool = Location::factory()->create(['city' => 'Cluj-Napoca']);

    spaceAt($pool, $swimming, SpaceAccessMode::OpenAccess, 25);

    $this->get(route('sports.show', ['slug' => $swimming->slug, 'city' => 'cluj-napoca']))
        ->assertInertia(fn ($page) => $page->where('surfaces', []));
});
