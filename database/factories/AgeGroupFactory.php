<?php

namespace Database\Factories;

use App\Models\AgeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgeGroup>
 */
class AgeGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('#-## ani'),
            'sort_order' => 0,
        ];
    }
}
