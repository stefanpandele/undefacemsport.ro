<?php

use App\Models\Club;
use App\Models\ClubSport;
use App\Models\Sport;

test('a club sport belongs to a club and a global sport', function () {
    $club = Club::factory()->create();
    $sport = Sport::factory()->create();

    $clubSport = $club->clubSports()->create(['sport_id' => $sport->id, 'description' => 'Înot pentru copii']);

    expect($clubSport)->toBeInstanceOf(ClubSport::class)
        ->and($clubSport->club->is($club))->toBeTrue()
        ->and($clubSport->sport->is($sport))->toBeTrue();
});

test('a free club can add one sport then hits the plan limit', function () {
    $club = Club::factory()->create(); // free plan: sports limit 1

    expect($club->canAddSport())->toBeTrue();

    $club->clubSports()->create(['sport_id' => Sport::factory()->create()->id]);

    expect($club->canAddSport())->toBeFalse();
});

test('a pro club can add sports up to its higher limit', function () {
    $club = Club::factory()->pro()->create(); // sports limit 5

    Sport::factory()->count(4)->create()->each(
        fn (Sport $sport) => $club->clubSports()->create(['sport_id' => $sport->id]),
    );

    expect($club->canAddSport())->toBeTrue(); // 4 < 5

    $club->clubSports()->create(['sport_id' => Sport::factory()->create()->id]);

    expect($club->canAddSport())->toBeFalse(); // 5 == 5
});

test('a premium club has no sport limit', function () {
    $club = Club::factory()->premium()->create();

    Sport::factory()->count(10)->create()->each(
        fn (Sport $sport) => $club->clubSports()->create(['sport_id' => $sport->id]),
    );

    expect($club->canAddSport())->toBeTrue();
});
