<?php

namespace Database\Seeders;

use App\Models\ClubApplication;
use Illuminate\Database\Seeder;

class ClubApplicationSeeder extends Seeder
{
    /**
     * Seed club applications in a mix of states so the /admin review flow has
     * data to work with. These are demo records — they are NOT run through the
     * real approval flow (no clubs/owners are created from them here).
     */
    public function run(): void
    {
        if (ClubApplication::query()->exists()) {
            return;
        }

        ClubApplication::factory()->count(8)->create();
        ClubApplication::factory()->approved()->count(3)->create();
        ClubApplication::factory()->rejected()->count(2)->create();
    }
}
