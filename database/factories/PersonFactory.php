<?php

namespace Database\Factories;

use App\Enums\PersonProfession;
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
            'profession' => PersonProfession::Coach,
            'role' => fake()->randomElement(['Antrenor principal', 'Antrenor', 'Antrenoare']),
            'bio' => fake()->sentence(),
            'offers_private_sessions' => false,
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Someone whose profession is not coaching — a practice's doctor,
     * physiotherapist or nutritionist. The job title follows the profession, so
     * the public card does not read "Antrenor principal" above a nutritionist.
     */
    public function professional(PersonProfession $profession): static
    {
        return $this->state(fn (): array => [
            'profession' => $profession,
            'role' => $profession->label(),
        ]);
    }
}
