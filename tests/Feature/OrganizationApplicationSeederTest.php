<?php

use App\Enums\OrganizationApplicationStatus;
use App\Enums\OrganizationType;
use App\Models\OrganizationApplication;
use Database\Seeders\OrganizationApplicationSeeder;

test('organization applications are seeded across pending, approved and rejected states', function () {
    $this->seed(OrganizationApplicationSeeder::class);

    expect(OrganizationApplication::count())->toBe(13)
        ->and(OrganizationApplication::where('status', OrganizationApplicationStatus::Approved->value)->count())->toBe(3)
        ->and(OrganizationApplication::where('status', OrganizationApplicationStatus::Rejected->value)->count())->toBe(2)
        ->and(OrganizationApplication::where('status', OrganizationApplicationStatus::Pending->value)->count())->toBe(8);
});

test('every organization type shows up in the seeded queue', function (OrganizationType $type) {
    $this->seed(OrganizationApplicationSeeder::class);

    expect(OrganizationApplication::where('type', $type->value)->exists())->toBeTrue();
})->with(OrganizationType::cases());

test('the organization application seeder is idempotent', function () {
    $this->seed(OrganizationApplicationSeeder::class);
    $this->seed(OrganizationApplicationSeeder::class);

    expect(OrganizationApplication::count())->toBe(13);
});
