<?php

use App\Enums\Plan;
use App\Models\Club;

test('a free club is limited and lacks paid features', function () {
    $club = Club::factory()->create();

    expect($club->plan)->toBe(Plan::Free)
        ->and($club->planAllows('gallery'))->toBeFalse()
        ->and($club->planLimit('sports'))->toBe(1)
        ->and($club->withinPlanLimit('sports', 0))->toBeTrue()
        ->and($club->withinPlanLimit('sports', 1))->toBeFalse();
});

test('a pro club unlocks features and higher limits', function () {
    $club = Club::factory()->pro()->create();

    expect($club->plan)->toBe(Plan::Pro)
        ->and($club->planAllows('gallery'))->toBeTrue()
        ->and($club->planAllows('custom_contacts'))->toBeFalse()
        ->and($club->planLimit('sports'))->toBe(5)
        ->and($club->withinPlanLimit('sports', 4))->toBeTrue()
        ->and($club->withinPlanLimit('sports', 5))->toBeFalse();
});

test('a premium club has features and unlimited limits', function () {
    $club = Club::factory()->premium()->create();

    expect($club->planAllows('custom_contacts'))->toBeTrue()
        ->and($club->planLimit('sports'))->toBeNull()
        ->and($club->withinPlanLimit('sports', 9999))->toBeTrue();
});

test('an unknown limit key resolves to not available', function () {
    $club = Club::factory()->create();

    expect($club->planLimit('nonexistent'))->toBe(0)
        ->and($club->withinPlanLimit('nonexistent', 0))->toBeFalse();
});
