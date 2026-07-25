<?php

namespace Database\Seeders;

use App\Models\County;
use Illuminate\Database\Seeder;

class CountySeeder extends Seeder
{
    /**
     * Seed the 42 Romanian counties (județe) from config/counties.php.
     * Idempotent — safe to re-run.
     */
    public function run(): void
    {
        if (County::query()->exists()) {
            return;
        }

        County::insert(array_map(
            fn (string $name): array => ['name' => $name],
            config('counties'),
        ));
    }
}
