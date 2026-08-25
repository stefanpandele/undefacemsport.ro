<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'role' => fake()->randomElement(['Antrenor principal', 'Antrenor', 'Antrenoare']),
            'bio' => fake()->sentence(),
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Someone who is not a coach — a practice's doctor, physiotherapist or
     * nutritionist. What they are is the job title itself.
     */
    public function professional(string $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }
}
