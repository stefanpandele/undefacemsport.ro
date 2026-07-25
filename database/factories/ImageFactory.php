<?php

namespace Database\Factories;

use App\Models\Image;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'clubs/gallery/'.fake()->uuid().'.jpg',
            'disk' => 's3',
            'collection' => fake()->randomElement(['gallery', 'facilities']),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
