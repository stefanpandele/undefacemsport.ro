<?php

namespace Database\Factories;

use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Specialty>
 */
class SpecialtyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Specialitate '.fake()->unique()->numberBetween(1, 100000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => '🩺',
            'color' => '#1D7FB8',
            'sort_order' => 0,
        ];
    }
}
