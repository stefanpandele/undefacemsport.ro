<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coach>
 */
class CoachFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->name(),
            'role' => fake()->randomElement(['Antrenor principal', 'Antrenor', 'Antrenoare']),
            'bio' => fake()->sentence(),
            'offers_private_sessions' => false,
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }
}
