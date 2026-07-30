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
            // Null by design: a curated or hand-entered place has no Google
            // identity until somebody geocodes it through the panel.
            'google_place_id' => null,
        ];
    }

    /**
     * Place the location at exact coordinates, for proximity tests.
     */
    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn (): array => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public function withPlaceId(string $placeId): static
    {
        return $this->state(fn (): array => ['google_place_id' => $placeId]);
    }
}
