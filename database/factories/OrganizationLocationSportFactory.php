<?php

namespace Database\Factories;

use App\Models\OrganizationLocation;
use App\Models\OrganizationLocationSport;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationLocationSport>
 */
class OrganizationLocationSportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_location_id' => OrganizationLocation::factory(),
            'sport_id' => Sport::factory(),
        ];
    }
}
