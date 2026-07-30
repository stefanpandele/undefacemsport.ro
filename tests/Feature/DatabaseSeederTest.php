<?php

use App\Enums\OrganizationType;
use App\Models\Location;
use App\Models\Organization;
use App\Models\ScheduleSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('seeding creates clubs, each with a master and members', function () {
    $this->seed();

    // 36 demo clubs + the 2 known login clubs + 10 venues + 8 practices.
    expect(Organization::count())->toBe(56)
        ->and(Organization::where('type', OrganizationType::Club)->count())->toBe(38)
        ->and(Organization::where('type', OrganizationType::Venue)->count())->toBe(10)
        ->and(Organization::where('type', OrganizationType::Practice)->count())->toBe(8);

    Organization::with('owner', 'users')->get()->each(function (Organization $organization): void {
        expect($organization->owner)->not->toBeNull()
            ->and($organization->users->count())->toBeGreaterThanOrEqual(1)
            ->and($organization->owner->isMasterOf($organization))->toBeTrue();
    });
});

test('seeding creates a known club representative owning a club', function () {
    $this->seed();

    $rep = User::where('email', 'club@email.com')->first();

    expect($rep)->not->toBeNull()
        ->and($rep->ownsAnyOrganization())->toBeTrue()
        ->and($rep->isConsumer())->toBeFalse();
});

test('seeding creates a platform admin and public consumers', function () {
    $this->seed();

    expect(User::where('is_admin', true)->exists())->toBeTrue();

    $consumer = User::where('email', 'user@email.com')->first();

    expect($consumer)->not->toBeNull()
        ->and($consumer->isConsumer())->toBeTrue();
});

test('re-running the seeders does not duplicate data', function () {
    $this->seed();
    $clubCount = Organization::count();
    $userCount = User::count();
    $locationCount = Location::count();
    $slotCount = ScheduleSlot::count();

    $this->seed();

    expect(Organization::count())->toBe($clubCount)
        ->and(User::count())->toBe($userCount)
        ->and(Location::count())->toBe($locationCount)
        ->and(ScheduleSlot::count())->toBe($slotCount);
});

test('every seeded location gets a slug', function () {
    // Regression: DatabaseSeeder used WithoutModelEvents, which suppressed the
    // `creating` hook that fills the non-nullable Location::$slug.
    $this->seed();

    expect(Location::whereNull('slug')->orWhere('slug', '')->count())->toBe(0)
        ->and(Location::count())->toBeGreaterThan(15);
});

test('seeding gives clubs a public profile to show', function () {
    $this->seed();

    // Clubs only: a venue has no sports, no age groups and no coaches, and
    // inventing them would be inventing an offer it does not make.
    Organization::where('type', OrganizationType::Club)
        ->with('organizationSports', 'organizationLocations', 'people', 'contacts')
        ->get()
        ->each(function (Organization $organization): void {
            expect($organization->organizationSports)->not->toBeEmpty()
                ->and($organization->organizationLocations)->not->toBeEmpty()
                ->and($organization->people)->not->toBeEmpty()
                ->and($organization->contacts)->not->toBeEmpty();
        });

    expect(ScheduleSlot::count())->toBeGreaterThan(50);
});

test('every seeded city holds at least two clubs, so a hall can be shared', function () {
    // The invariant that ties OrganizationSeeder's target to LocationSeeder's city list:
    // widening the venues without widening the clubs once left every city with a
    // single club, and a lone club shares a hall with nobody.
    $this->seed();

    $clubsPerCity = DB::table('organization_location')
        ->join('locations', 'locations.id', '=', 'organization_location.location_id')
        ->join('organizations', 'organizations.id', '=', 'organization_location.organization_id')
        // Clubs only, or a seeded venue would prop the count up and the invariant
        // this test exists to guard would stop being guarded.
        ->where('organizations.type', OrganizationType::Club)
        ->groupBy('locations.city')
        ->selectRaw('locations.city, count(distinct organization_location.organization_id) as clubs')
        ->pluck('clubs', 'city');

    expect($clubsPerCity)->not->toBeEmpty();

    $clubsPerCity->each(fn (int $clubs, string $city) => expect($clubs)
        ->toBeGreaterThanOrEqual(2, $city.' has only '.$clubs.' club(s)'));
});

test('seeding puts clubs in the same hall on the same sport and interval', function () {
    $this->seed();

    // Two clubs teaching one sport at one venue is what the location page's
    // occupancy badge and anonymous "hall is taken" card are built on.
    $shared = DB::table('schedule_slots')
        ->join('organization_location_sport', 'organization_location_sport.id', '=', 'schedule_slots.organization_location_sport_id')
        ->join('organization_location', 'organization_location.id', '=', 'organization_location_sport.organization_location_id')
        ->select('organization_location.location_id', 'organization_location_sport.sport_id', 'schedule_slots.start_time', 'schedule_slots.end_time')
        ->selectRaw('count(distinct organization_location.organization_id) as clubs')
        ->groupBy('organization_location.location_id', 'organization_location_sport.sport_id', 'schedule_slots.start_time', 'schedule_slots.end_time')
        ->having('clubs', '>', 1)
        ->get();

    expect($shared)->not->toBeEmpty();
});

test('seeding tops up a database that already holds a few clubs', function () {
    // Regression: OrganizationSeeder used to bail out entirely when any club existed,
    // so a database left short by an earlier failed run stayed short forever —
    // too few clubs per city for any of them to share a hall.
    Organization::factory()->count(2)->create();
    Organization::factory()->premium()->create();

    $this->seed();

    expect(Organization::where('type', OrganizationType::Club)->count())->toBe(38);

    Organization::where('type', OrganizationType::Club)->with('organizationSports')->get()->each(
        fn (Organization $organization) => expect($organization->organizationSports)->not->toBeEmpty(),
    );
});

test('seeded clubs stay within their plan limits', function () {
    $this->seed();

    Organization::withCount('organizationSports', 'organizationLocations', 'spaces')->get()->each(function (Organization $organization): void {
        $sportLimit = $organization->planLimit('sports');
        $locationLimit = $organization->planLimit('locations');
        $spaceLimit = $organization->planLimit('spaces');

        if ($sportLimit !== null) {
            expect($organization->organization_sports_count)->toBeLessThanOrEqual($sportLimit);
        }

        if ($locationLimit !== null) {
            expect($organization->organization_locations_count)->toBeLessThanOrEqual($locationLimit);
        }

        if ($spaceLimit !== null) {
            expect($organization->spaces_count)->toBeLessThanOrEqual($spaceLimit);
        }
    });
});
