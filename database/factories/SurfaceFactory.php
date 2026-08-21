<?php

namespace Database\Factories;

use App\Models\Surface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Surface>
 */
class SurfaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Suprafață '.fake()->unique()->numberBetween(1, 100000),
            'sort_order' => 0,
        ];
    }
}
