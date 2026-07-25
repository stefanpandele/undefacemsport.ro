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

        // Mix of plans so the club panel's subscription limits are testable.
        Club::factory()->count(2)->create();            // free  (1 sport)
        Club::factory()->pro()->count(2)->create();     // pro   (5 sports)
        Club::factory()->premium()->count(1)->create(); // premium (unlimited)
    }
}
