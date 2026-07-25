<?php

use App\Models\Sport;
use Database\Seeders\SportSeeder;
use Illuminate\Support\Facades\Lang;

test('sports are seeded with unique slugs', function () {
    $this->seed(SportSeeder::class);

    expect(Sport::count())->toBeGreaterThan(0)
        ->and(Sport::pluck('slug')->duplicates())->toBeEmpty();
});

test('the sport seeder is idempotent', function () {
    $this->seed(SportSeeder::class);
    $count = Sport::count();

    $this->seed(SportSeeder::class);

    expect(Sport::count())->toBe($count);
});

test('every seeded sport has a translation in both locales', function () {
    $this->seed(SportSeeder::class);

    $slugs = Sport::pluck('slug');

    foreach (['ro', 'en'] as $locale) {
        app()->setLocale($locale);

        $missing = $slugs->reject(fn (string $slug): bool => Lang::has('sports.'.$slug));

        expect($missing)->toBeEmpty("Missing {$locale} translations: ".$missing->implode(', '));
    }
});
