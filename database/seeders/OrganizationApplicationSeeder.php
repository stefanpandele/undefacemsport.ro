<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\OrganizationApplication;
use Illuminate\Database\Seeder;

class OrganizationApplicationSeeder extends Seeder
{
    /**
     * Seed club applications in a mix of states so the /admin review flow has
     * data to work with. These are demo records — they are NOT run through the
     * real approval flow (no clubs/owners are created from them here).
     */
    public function run(): void
    {
        if (OrganizationApplication::query()->exists()) {
            return;
        }

        OrganizationApplication::factory()->ofType(OrganizationType::Club)->count(4)->create();
        OrganizationApplication::factory()->ofType(OrganizationType::Venue)->count(2)->create();
        OrganizationApplication::factory()->ofType(OrganizationType::Practice)->count(2)->create();
        OrganizationApplication::factory()->approved()->count(3)->create();
        OrganizationApplication::factory()->rejected()->count(2)->create();
    }
}
