<?php

use App\Enums\ScheduleSlotKind;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Level;
use App\Models\Organization;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use Database\Seeders\LevelSeeder;

/**
 * One training hour at an address, which is where a club's groups and levels
 * are recorded now.
 */
function slotFor(OrganizationLocationSport $taught, string $start, AgeGroup $ageGroup, ?Level $level): ScheduleSlot
{
    return ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Training,
        'organization_id' => $taught->organizationLocation->organization_id,
        'organization_location_sport_id' => $taught->getKey(),
        'day_of_week' => Weekday::Monday,
        'start_time' => $start,
        'end_time' => '23:00',
        'age_group_id' => $ageGroup->getKey(),
        'level_id' => $level?->getKey(),
    ]);
}

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
    // The whole reason for a second column: a child and an adult can both be
    // beginners, and both can be competing. One column would have forced
    // "8–14 ani inițiere" as a group distinct from "8–14 ani performanță".
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();
    $presence = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A',
    ], [$sport->getKey()]);
    $taught = $presence->organizationLocationSports->first();

    $children = AgeGroup::create(['name' => '8–14 ani', 'sort_order' => 0]);
    $adults = AgeGroup::create(['name' => 'Adulți', 'sort_order' => 1]);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $competition = Level::create(['name' => 'Performanță', 'sort_order' => 1]);

    slotFor($taught, '17:00', $children, $competition);
    slotFor($taught, '19:00', $adults, $beginner);

    $slots = $taught->refresh()->scheduleSlots;

    expect(ScheduleSlot::ageGroupNames($slots))->toBe(['8–14 ani', 'Adulți'])
        ->and(ScheduleSlot::levelNames($slots))->toBe(['Inițiere', 'Performanță'])
        ->and($beginner->scheduleSlots)->toHaveCount(1);
});

test('a level belongs to the hour, so two addresses of one club can differ', function () {
    // The reason the declared list had to go: a club with a children's pool and
    // an adults' pool used to show every level at both.
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();

    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);
    $competition = Level::create(['name' => 'Performanță', 'sort_order' => 1]);
    $children = AgeGroup::create(['name' => '8–14 ani', 'sort_order' => 0]);
    $adults = AgeGroup::create(['name' => 'Adulți', 'sort_order' => 1]);

    $pool = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A',
    ], [$sport->getKey()])->organizationLocationSports->first();
    $hall = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Bazinul B',
    ], [$sport->getKey()])->organizationLocationSports->first();

    slotFor($pool, '17:00', $children, $beginner);
    slotFor($hall, '20:00', $adults, $competition);

    expect(ScheduleSlot::levelNames($pool->refresh()->scheduleSlots))->toBe(['Inițiere'])
        ->and(ScheduleSlot::levelNames($hall->refresh()->scheduleSlots))->toBe(['Performanță'])
        ->and(ScheduleSlot::ageGroupNames($pool->scheduleSlots))->toBe(['8–14 ani'])
        ->and(ScheduleSlot::ageGroupNames($hall->scheduleSlots))->toBe(['Adulți']);
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

test('a level nobody filled in is skipped rather than shown as blank', function () {
    // The column stays nullable — a retired level nulls it, and an access
    // tariff never had one — so the derived list has to drop the gap silently.
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();
    $presence = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Sala B',
    ], [$sport->getKey()]);
    $taught = $presence->organizationLocationSports->first();

    $group = AgeGroup::create(['name' => 'Adulți', 'sort_order' => 0]);
    $beginner = Level::create(['name' => 'Inițiere', 'sort_order' => 0]);

    slotFor($taught, '17:00', $group, $beginner);
    slotFor($taught, '19:00', $group, null);

    expect(ScheduleSlot::levelNames($taught->refresh()->scheduleSlots))->toBe(['Inițiere'])
        ->and(ScheduleSlot::ageGroupNames($taught->scheduleSlots))->toBe(['Adulți']);
});

test('the levels a club teaches reach its public page', function () {
    $this->seed();

    $slot = ScheduleSlot::query()
        ->where('kind', ScheduleSlotKind::Training)
        ->whereNotNull('level_id')
        ->with(['level', 'organizationLocationSport.sport', 'organizationLocationSport.organizationLocation.organization'])
        ->first();

    $organization = $slot->organizationLocationSport->organizationLocation->organization;
    $sportSlug = $slot->organizationLocationSport->sport->slug;

    $this->get(route('organizations.show', $organization->slug))->assertInertia(
        function ($page) use ($organization, $sportSlug, $slot) {
            $courses = collect($page->toArray()['props']['organization']['courses']);
            $course = $courses->firstWhere('key', $sportSlug);

            expect($courses)->toHaveCount($organization->organizationSports()->count())
                ->and($course['levels'])->toContain($slot->level->name);

            return $page;
        },
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

test('every seeded address teaches a run of levels starting from the bottom', function () {
    // A club training competitors almost always also runs a beginners' group;
    // one offering only "performanță" is not a real club. The run is drawn per
    // address now, so that is where it has to hold.
    $this->seed();

    $first = Level::orderBy('sort_order')->value('name');

    OrganizationLocationSport::query()
        ->has('scheduleSlots')
        ->with(['sport', 'scheduleSlots.level', 'scheduleSlots.ageGroup'])
        ->get()
        ->each(function (OrganizationLocationSport $taught) use ($first): void {
            $levels = ScheduleSlot::levelNames($taught->scheduleSlots);

            // toContain() treats a second argument as another expected value,
            // not as a message, so the diagnosis goes in the assertion above it.
            expect($levels)->not->toBeEmpty($taught->sport->name.' is taught at no level at all');
            expect($levels)->toContain($first);
        });
});

test('every seeded training names both its group and its level', function () {
    // Nothing else records them any more: a blank column is a chip missing from
    // the club's own page.
    $this->seed();

    $blank = ScheduleSlot::query()
        ->where('kind', ScheduleSlotKind::Training)
        ->where(fn ($query) => $query->whereNull('level_id')->orWhereNull('age_group_id'))
        ->count();

    expect($blank)->toBe(0);
});
