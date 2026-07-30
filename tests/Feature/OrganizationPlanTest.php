<?php

use App\Enums\Plan;
use App\Models\Organization;

test('a free club is limited and lacks paid features', function () {
    $organization = Organization::factory()->create();

    expect($organization->plan)->toBe(Plan::Free)
        ->and($organization->planAllows('sport_covers'))->toBeFalse()
        ->and($organization->planLimit('sports'))->toBe(1)
        ->and($organization->withinPlanLimit('sports', 0))->toBeTrue()
        ->and($organization->withinPlanLimit('sports', 1))->toBeFalse();
});

test('every plan may show the same number of gallery photos', function () {
    // How good a club's listing looks is not for sale: two clubs compared side
    // by side in the same hall must be able to show the same many photos.
    $limits = collect(Plan::cases())->map(
        fn (Plan $plan): ?int => Organization::factory()->create(['plan' => $plan])->planLimit('gallery_images'),
    );

    expect($limits->unique())->toHaveCount(1)
        ->and($limits->first())->toBe(20);
});

test('a free club may use the gallery', function () {
    expect(Organization::factory()->create()->planAllows('gallery'))->toBeTrue();
});

test('a pro club unlocks features and higher limits', function () {
    $organization = Organization::factory()->pro()->create();

    expect($organization->plan)->toBe(Plan::Pro)
        ->and($organization->planAllows('gallery'))->toBeTrue()
        ->and($organization->planAllows('custom_contacts'))->toBeFalse()
        ->and($organization->planLimit('sports'))->toBe(5)
        ->and($organization->withinPlanLimit('sports', 4))->toBeTrue()
        ->and($organization->withinPlanLimit('sports', 5))->toBeFalse();
});

test('a premium club has features and unlimited limits', function () {
    $organization = Organization::factory()->premium()->create();

    expect($organization->planAllows('custom_contacts'))->toBeTrue()
        ->and($organization->planLimit('sports'))->toBeNull()
        ->and($organization->withinPlanLimit('sports', 9999))->toBeTrue();
});

test('an unknown limit key resolves to not available', function () {
    $organization = Organization::factory()->create();

    expect($organization->planLimit('nonexistent'))->toBe(0)
        ->and($organization->withinPlanLimit('nonexistent', 0))->toBeFalse();
});
