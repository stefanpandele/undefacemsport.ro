<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Service;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->practice(),
            'specialty_id' => Specialty::factory(),
            'name' => 'Consultație '.fake()->unique()->numberBetween(1, 100000),
            'duration_minutes' => 50,
            'price' => 200,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => ['price' => 0]);
    }

    public function unpriced(): static
    {
        return $this->state(fn (): array => ['price' => null]);
    }
}
