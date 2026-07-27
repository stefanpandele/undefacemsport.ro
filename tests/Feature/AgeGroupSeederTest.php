<?php

use App\Models\AgeGroup;
use Database\Seeders\AgeGroupSeeder;

test('the age group seeder is idempotent and seeds ordered groups', function () {
    (new AgeGroupSeeder)->run();
    (new AgeGroupSeeder)->run();

    expect(AgeGroup::count())->toBe(7)
        ->and(AgeGroup::where('name', '0–2 ani')->count())->toBe(1)
        ->and(AgeGroup::orderBy('sort_order')->first()->name)->toBe('0–2 ani');
});
