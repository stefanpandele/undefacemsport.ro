<?php

use App\Enums\LocationCorrectionStatus;
use App\Models\Location;
use App\Models\LocationCorrection;
use Database\Seeders\LocationCorrectionSeeder;

test('the seeder fills the review queue with volume and every status', function () {
    $this->seed();

    $byStatus = LocationCorrection::query()
        ->selectRaw('status, count(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    expect(LocationCorrection::count())->toBeGreaterThan(20)
        ->and($byStatus->get(LocationCorrectionStatus::Pending->value))->toBeGreaterThan(0)
        ->and($byStatus->get(LocationCorrectionStatus::Approved->value))->toBeGreaterThan(0)
        ->and($byStatus->get(LocationCorrectionStatus::Rejected->value))->toBeGreaterThan(0);
});

test('every seeded correction points at a real location and proposes a change', function () {
    $this->seed();

    LocationCorrection::with('location')->get()->each(function (LocationCorrection $correction): void {
        expect($correction->location)->not->toBeNull()
            ->and($correction->suggested_value)->not->toBe('')
            // A proposal identical to the current value would be nothing to review.
            ->and($correction->suggested_value)->not->toBe($correction->location->{$correction->field->value});
    });
});

test('reviewed seeded corrections carry a reviewer, pending ones do not', function () {
    $this->seed();

    LocationCorrection::all()->each(function (LocationCorrection $correction): void {
        $correction->isPending()
            ? expect($correction->reviewed_by)->toBeNull()->and($correction->reviewed_at)->toBeNull()
            : expect($correction->reviewed_by)->not->toBeNull()->and($correction->reviewed_at)->not->toBeNull();
    });
});

test('re-running the correction seeder files nothing twice', function () {
    $this->seed();
    $count = LocationCorrection::count();

    (new LocationCorrectionSeeder)->run();

    expect(LocationCorrection::count())->toBe($count);
});

test('the correction seeder leaves the curated location names alone', function () {
    // Applied history is recorded, not replayed — replaying it would rewrite the
    // seeded venue names into the deliberately wrong values it proposes.
    $this->seed();

    expect(Location::query()->where('name', 'Cluj Arena')->exists())->toBeTrue()
        ->and(Location::query()->where('name', 'like', '%— Sala 2')->exists())->toBeFalse();
});

test('the correction seeder does nothing on an empty database', function () {
    (new LocationCorrectionSeeder)->run();

    expect(LocationCorrection::count())->toBe(0);
});
