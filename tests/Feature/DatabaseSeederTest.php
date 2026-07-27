<?php

use App\Models\Club;
use App\Models\Location;
use App\Models\ScheduleSlot;
use App\Models\User;

test('seeding creates clubs, each with a master and members', function () {
    $this->seed();

    expect(Club::count())->toBe(16); // 14 demo clubs + the 2 known login clubs

    Club::with('owner', 'users')->get()->each(function (Club $club): void {
        expect($club->owner)->not->toBeNull()
            ->and($club->users->count())->toBeGreaterThanOrEqual(1)
            ->and($club->owner->isMasterOf($club))->toBeTrue();
    });
});

test('seeding creates a known club representative owning a club', function () {
    $this->seed();

    $rep = User::where('email', 'club@email.com')->first();

    expect($rep)->not->toBeNull()
        ->and($rep->ownsAnyClub())->toBeTrue()
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
    $clubCount = Club::count();
    $userCount = User::count();
    $locationCount = Location::count();
    $slotCount = ScheduleSlot::count();

    $this->seed();

    expect(Club::count())->toBe($clubCount)
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

    Club::with('clubSports', 'clubLocations', 'coaches', 'contacts')
        ->get()
        ->each(function (Club $club): void {
            expect($club->clubSports)->not->toBeEmpty()
                ->and($club->clubLocations)->not->toBeEmpty()
                ->and($club->coaches)->not->toBeEmpty()
                ->and($club->contacts)->not->toBeEmpty();
        });

    expect(ScheduleSlot::count())->toBeGreaterThan(50);
});

test('seeded clubs stay within their plan limits', function () {
    $this->seed();

    Club::withCount('clubSports', 'clubLocations')->get()->each(function (Club $club): void {
        $sportLimit = $club->planLimit('sports');
        $locationLimit = $club->planLimit('locations');

        if ($sportLimit !== null) {
            expect($club->club_sports_count)->toBeLessThanOrEqual($sportLimit);
        }

        if ($locationLimit !== null) {
            expect($club->club_locations_count)->toBeLessThanOrEqual($locationLimit);
        }
    });
});
