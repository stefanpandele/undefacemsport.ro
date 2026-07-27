<?php

use App\Enums\Plan;
use App\Models\Club;

test('a free club is limited and lacks paid features', function () {
    $club = Club::factory()->create();

    expect($club->plan)->toBe(Plan::Free)
        ->and($club->planAllows('sport_covers'))->toBeFalse()
        ->and($club->planLimit('sports'))->toBe(1)
        ->and($club->withinPlanLimit('sports', 0))->toBeTrue()
        ->and($club->withinPlanLimit('sports', 1))->toBeFalse();
});

test('every plan may show the same number of gallery photos', function () {
    // How good a club's listing looks is not for sale: two clubs compared side
    // by side in the same hall must be able to show the same many photos.
    $limits = collect(Plan::cases())->map(
        fn (Plan $plan): ?int => Club::factory()->create(['plan' => $plan])->planLimit('gallery_images'),
    );

    expect($limits->unique())->toHaveCount(1)
        ->and($limits->first())->toBe(20);
});

test('a free club may use the gallery', function () {
    expect(Club::factory()->create()->planAllows('gallery'))->toBeTrue();
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
