<?php

namespace Database\Seeders;

use App\Models\Club;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    /**
     * Seed a handful of clubs. They start ownerless; ClubUserSeeder attaches
     * a master and members to them.
     */
    public function run(): void
    {
        if (Club::query()->exists()) {
            return;
        }

        // Mix of plans so the club panel's subscription limits are testable,
        // in enough volume for the public explore/location pages to look real.
        Club::factory()->count(4)->create();            // free  (1 sport)
        Club::factory()->pro()->count(7)->create();     // pro   (5 sports)
        Club::factory()->premium()->count(3)->create(); // premium (unlimited)
    }
}
