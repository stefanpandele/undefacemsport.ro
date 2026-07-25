<?php

use App\Enums\ClubApplicationStatus;
use App\Models\ClubApplication;
use Database\Seeders\ClubApplicationSeeder;

test('club applications are seeded across pending, approved and rejected states', function () {
    $this->seed(ClubApplicationSeeder::class);

    expect(ClubApplication::count())->toBe(13)
        ->and(ClubApplication::where('status', ClubApplicationStatus::Approved->value)->count())->toBe(3)
        ->and(ClubApplication::where('status', ClubApplicationStatus::Rejected->value)->count())->toBe(2)
        ->and(ClubApplication::where('status', ClubApplicationStatus::Pending->value)->count())->toBe(8);
});

test('the club application seeder is idempotent', function () {
    $this->seed(ClubApplicationSeeder::class);
    $this->seed(ClubApplicationSeeder::class);

    expect(ClubApplication::count())->toBe(13);
});
