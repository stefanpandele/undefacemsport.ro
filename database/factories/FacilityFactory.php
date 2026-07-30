<?php

namespace Database\Factories;

use App\Enums\FacilityStatus;
use App\Models\Facility;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' '.fake()->word(),
            'icon' => fake()->randomElement(['🅿️', '🚿', '💡', '📶']),
            'status' => FacilityStatus::Approved,
            'sort_order' => 0,
        ];
    }

    /**
     * A club's suggestion, waiting for an admin to approve it.
     */
    public function pending(?Organization $organization = null): static
    {
        return $this->state(fn (): array => [
            'status' => FacilityStatus::Pending,
            'suggested_by_organization_id' => $organization?->getKey() ?? Organization::factory(),
        ]);
    }
}
