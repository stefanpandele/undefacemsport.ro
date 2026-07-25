<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Baza sportivă '.fake()->unique()->numberBetween(1, 100000),
            'county' => fake()->randomElement(config('counties')),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(43.6, 48.3),
            'longitude' => fake()->longitude(20.2, 29.7),
        ];
    }
}
