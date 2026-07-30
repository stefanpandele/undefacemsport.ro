<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationLocation>
 */
class OrganizationLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'location_id' => Location::factory(),
        ];
    }
}
