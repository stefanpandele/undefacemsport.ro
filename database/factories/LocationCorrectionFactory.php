<?php

namespace Database\Factories;

use App\Enums\LocationCorrectionField;
use App\Enums\LocationCorrectionStatus;
use App\Models\Location;
use App\Models\LocationCorrection;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationCorrection>
 */
class LocationCorrectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'organization_id' => Organization::factory(),
            'field' => LocationCorrectionField::Name,
            'suggested_value' => 'Sala Sporturilor '.fake()->unique()->numberBetween(1, 100000),
            'note' => fake()->boolean(70) ? fake()->sentence() : null,
            'status' => LocationCorrectionStatus::Pending,
        ];
    }

    public function forField(LocationCorrectionField $field, string $value): static
    {
        return $this->state(fn (): array => [
            'field' => $field,
            'suggested_value' => $value,
        ]);
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => LocationCorrectionStatus::Approved,
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'reviewed_by' => $reviewer?->getKey() ?? User::factory(),
        ]);
    }

    public function rejected(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => LocationCorrectionStatus::Rejected,
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'reviewed_by' => $reviewer?->getKey() ?? User::factory(),
        ]);
    }
}
