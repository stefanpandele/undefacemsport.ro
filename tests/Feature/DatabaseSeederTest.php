<?php

use App\Models\Club;
use App\Models\User;

test('seeding creates clubs, each with a master and members', function () {
    $this->seed();

    expect(Club::count())->toBe(7); // 5 demo clubs + the 2 known login clubs

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

    $this->seed();

    expect(Club::count())->toBe($clubCount)
        ->and(User::count())->toBe($userCount);
});
