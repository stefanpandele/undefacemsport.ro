<?php

use App\Models\County;
use App\Models\Locality;
use Database\Seeders\CountySeeder;
use Database\Seeders\LocalitySeeder;

test('the 42 counties and their localities are seeded', function () {
    $this->seed(CountySeeder::class);
    $this->seed(LocalitySeeder::class);

    expect(County::count())->toBe(42)
        ->and(Locality::count())->toBeGreaterThan(13000);

    $alba = County::where('name', 'Alba')->firstOrFail();

    expect($alba->localities()->where('name', 'Alba Iulia')->exists())->toBeTrue();
});

test('the county and locality seeders are idempotent', function () {
    $this->seed(CountySeeder::class);
    $this->seed(LocalitySeeder::class);
    $counties = County::count();
    $localities = Locality::count();

    $this->seed(CountySeeder::class);
    $this->seed(LocalitySeeder::class);

    expect(County::count())->toBe($counties)
        ->and(Locality::count())->toBe($localities);
});
