<?php

namespace Database\Factories;

use App\Models\OrganizationSport;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSport>
 */
class OrganizationSportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sport_id' => Sport::factory(),
            'sort_order' => 0,
        ];
    }
}
