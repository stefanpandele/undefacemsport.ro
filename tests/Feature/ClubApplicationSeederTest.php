<?php

use App\Enums\OrganizationApplicationStatus;
use App\Models\OrganizationApplication;
use Database\Seeders\OrganizationApplicationSeeder;

test('club applications are seeded across pending, approved and rejected states', function () {
    $this->seed(OrganizationApplicationSeeder::class);

    expect(OrganizationApplication::count())->toBe(13)
        ->and(OrganizationApplication::where('status', OrganizationApplicationStatus::Approved->value)->count())->toBe(3)
        ->and(OrganizationApplication::where('status', OrganizationApplicationStatus::Rejected->value)->count())->toBe(2)
        ->and(OrganizationApplication::where('status', OrganizationApplicationStatus::Pending->value)->count())->toBe(8);
});

test('the club application seeder is idempotent', function () {
    $this->seed(OrganizationApplicationSeeder::class);
    $this->seed(OrganizationApplicationSeeder::class);

    expect(OrganizationApplication::count())->toBe(13);
});
