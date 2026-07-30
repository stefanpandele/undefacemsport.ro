<?php

use App\Enums\ScheduleSlotKind;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Level;
use App\Models\Organization;
use App\Models\OrganizationSport;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use Database\Seeders\LevelSeeder;

test('the levels are seeded in the order a beginner reads them', function () {
    $this->seed(LevelSeeder::class);

    expect(Level::orderBy('sort_order')->pluck('name')->all())->toBe([
        'Inițiere',
        'Grupă de copii',
        'Amatori',
        'Avansați',
        'Performanță',
    ]);
});

test('the level seeder is idempotent', function () {
    $this->seed(LevelSeeder::class);
    $count = Level::count();

    $this->seed(LevelSeeder::class);

    expect(Level::count())->toBe($count);
});

test('level and age group are independent axes', function () {
    // The whole reason for a second table: a child and an adult can both be
    // beginners, and both can be competing. One column would have forced
    // "8–14 ani inițiere" as a group distinct from "8–14 ani performanță".
    $sport = Sport::factory()->create();
    $organizationSport = Organization::factory()->create()
        ->organizationSports()
        ->create(['sport_id' => $sport->getKey(), 'sort_order' => 0]);

    $children = AgeGroup::create(['name' => '8–14 ani', 'sort_order' => 0]);
    $adults = AgeGroup::create(['name' => 'Adulți', 'sort_order' => 1]);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $competition = Level::create(['name' => 'Performanță', 'sort_order' => 1]);

    $organizationSport->ageGroups()->sync([$children->getKey(), $adults->getKey()]);
    $organizationSport->levels()->sync([$beginner->getKey(), $competition->getKey()]);

    expect($organizationSport->refresh()->ageGroups)->toHaveCount(2)
        ->and($organizationSport->levels)->toHaveCount(2)
        ->and($beginner->organizationSports->first()->is($organizationSport))->toBeTrue();
});

test('a level belongs to the sport a club teaches, not to the club', function () {
    // One club commonly runs beginners for children and competition for juniors,
    // so the level sits on organization_sport rather than on the organization.
    $organization = Organization::factory()->create();
    $swimming = $organization->organizationSports()->create([
        'sport_id' => Sport::factory()->create()->getKey(),
        'sort_order' => 0,
    ]);
    $basketball = $organization->organizationSports()->create([
        'sport_id' => Sport::factory()->create()->getKey(),
        'sort_order' => 1,
    ]);

    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $competition = Level::create(['name' => 'Performanță', 'sort_order' => 1]);

    $swimming->levels()->sync([$competition->getKey()]);
    $basketball->levels()->sync([$beginner->getKey()]);

    expect($swimming->refresh()->levels->pluck('name')->all())->toBe(['Performanță'])
        ->and($basketball->refresh()->levels->pluck('name')->all())->toBe(['Inițiere']);
});

test('a schedule slot can name its level, and survives the level going away', function () {
    $level = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();

    $presence = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A',
    ], [$sport->getKey()]);

    $slot = ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Training,
        'organization_id' => $organization->getKey(),
        'organization_location_sport_id' => $presence->organizationLocationSports->first()->getKey(),
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:30',
        'level_id' => $level->getKey(),
    ]);

    expect($slot->level->is($level))->toBeTrue();

    // nullOnDelete: retiring a level from the vocabulary must not delete
    // anybody's schedule.
    $level->delete();

    expect($slot->refresh()->level_id)->toBeNull()
        ->and(ScheduleSlot::count())->toBe(1);
});

test('a slot without a level is fine — it is optional, unlike the age group', function () {
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();

    $presence = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Sala B',
    ], [$sport->getKey()]);

    $slot = ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Training,
        'organization_id' => $organization->getKey(),
        'organization_location_sport_id' => $presence->organizationLocationSports->first()->getKey(),
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:30',
    ]);

    expect($slot->level)->toBeNull();
});

test('the levels a club offers reach the public club page', function () {
    $this->seed();

    $organizationSport = OrganizationSport::query()->has('levels')->with('sport')->first();
    $organization = $organizationSport->organization;

    $this->get(route('clubs.show', $organization->slug))->assertInertia(
        fn ($page) => $page->has(
            'club.sportDetails.'.$organizationSport->sport->slug.'.levels',
            $organizationSport->levels->count(),
        ),
    );
});

test('the level of each training reaches the location page schedule', function () {
    $this->seed();

    $slot = ScheduleSlot::query()
        ->where('kind', ScheduleSlotKind::Training)
        ->whereNotNull('level_id')
        ->with(['level', 'organizationLocationSport.organizationLocation.location'])
        ->first();

    $location = $slot->organizationLocationSport->organizationLocation->location;

    $this->get(route('locations.show', $location->slug))->assertInertia(function ($page) use ($slot) {
        $found = collect($page->toArray()['props']['location']['clubs'])
            ->flatMap(fn (array $club): array => $club['schedule'])
            ->flatMap(fn (array $day): array => $day['slots'])
            ->pluck('level')
            ->filter()
            ->contains($slot->level->name);

        expect($found)->toBeTrue('The level never reached the schedule payload.');

        return $page;
    });
});

test('every seeded club offers a run of levels starting from the bottom', function () {
    // A club training competitors almost always also runs a beginners' group;
    // one offering only "performanță" is not a real club.
    $this->seed();

    $first = Level::orderBy('sort_order')->value('name');

    // toContain() treats a second argument as another expected value, not as a
    // message, so the diagnosis goes in the assertion above it.
    OrganizationSport::with('levels', 'sport')->get()->each(function (OrganizationSport $sport) use ($first): void {
        expect($sport->levels)->not->toBeEmpty($sport->sport->name.' offers no level at all');
        expect($sport->levels->pluck('name'))->toContain($first);
    });
});

test('a seeded slot never claims a level its club does not teach', function () {
    $this->seed();

    ScheduleSlot::query()
        ->where('kind', ScheduleSlotKind::Training)
        ->whereNotNull('level_id')
        ->with(['organizationLocationSport.organizationLocation.organization.organizationSports.levels'])
        ->get()
        ->each(function (ScheduleSlot $slot): void {
            $offered = $slot->organizationLocationSport
                ->organizationLocation
                ->organization
                ->organizationSports
                ->firstWhere('sport_id', $slot->organizationLocationSport->sport_id)
                ?->levels
                ->pluck('id');

            expect($offered)->toContain($slot->level_id);
        });
});
