<?php

use App\Enums\OrganizationType;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Models\Organization;
use App\Models\ScheduleSlot;
use App\Models\Space;
use Database\Seeders\SpaceSeeder;

test('seeding gives every venue something to sell', function () {
    $this->seed();

    $venues = Organization::query()->offering(OrganizationType::Venue)->withCount('spaces')->get();

    expect($venues)->not->toBeEmpty();

    $venues->each(fn (Organization $venue) => expect($venue->spaces_count)
        ->toBeGreaterThan(0, $venue->name.' has nothing to sell'));
});

test('seeding puts both managed and free public spaces on the map', function () {
    $this->seed();

    $free = Space::query()->unmanaged()->with('accessSlots')->get()
        ->filter(fn (Space $space): bool => $space->isFree());

    expect(Space::query()->managed()->count())->toBeGreaterThan(0)
        ->and(Space::query()->unmanaged()->count())->toBeGreaterThan(0)
        // The reason `organization_location_id` is nullable at all.
        ->and($free)->not->toBeEmpty();
});

test('every seeded space has a tariff, so none of them is unlistable', function () {
    $this->seed();

    Space::with('accessSlots')->get()->each(
        fn (Space $space) => expect($space->accessSlots)->not->toBeEmpty($space->name.' has no tariff'),
    );
});

test('every seeded tariff says how you get in', function () {
    $this->seed();

    $modeless = ScheduleSlot::query()
        ->where('kind', ScheduleSlotKind::Access)
        ->whereNull('access_mode')
        ->count();

    expect($modeless)->toBe(0);
});

test('seeded hours are filed as access, never as trainings', function () {
    $this->seed();

    $misfiled = ScheduleSlot::query()
        ->whereNotNull('space_id')
        ->where('kind', ScheduleSlotKind::Training)
        ->whereNull('organization_location_sport_id')
        ->count();

    expect($misfiled)->toBe(0);
});

test('at least one seeded space charges less in the morning', function () {
    // What makes the public page say "de la 35 lei" instead of quoting a rate
    // that is wrong all morning.
    $this->seed();

    $varying = Space::with('accessSlots')->get()->filter(
        fn (Space $space): bool => $space->hasVaryingPrice(),
    );

    expect($varying)->not->toBeEmpty()
        ->and($varying->first()->priceFromLabel())->toStartWith('de la ');
});

test('both access modes are represented', function () {
    $this->seed();

    expect(Space::query()->offering(SpaceAccessMode::OpenAccess)->count())->toBeGreaterThan(0)
        ->and(Space::query()->offering(SpaceAccessMode::ExclusiveRental)->count())->toBeGreaterThan(0);
});

test('the review queue has something in it after seeding', function () {
    $this->seed();

    expect(Space::query()->stale()->count())->toBeGreaterThan(0);
});

test('re-running the space seeder creates nothing twice', function () {
    $this->seed();
    $spaces = Space::count();
    $slots = ScheduleSlot::count();

    (new SpaceSeeder)->run();

    expect(Space::count())->toBe($spaces)
        ->and(ScheduleSlot::count())->toBe($slots);
});

test('the space seeder does nothing without locations', function () {
    (new SpaceSeeder)->run();

    expect(Space::count())->toBe(0);
});
