<?php

namespace Database\Factories;

use App\Models\ClubSport;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubSport>
 */
class ClubSportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sport_id' => Sport::factory(),
            'description' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
