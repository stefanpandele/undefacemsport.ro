<?php

namespace Database\Factories;

use App\Enums\LocationClaimStatus;
use App\Models\Location;
use App\Models\LocationClaim;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationClaim>
 */
class LocationClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'organization_id' => Organization::factory()->venue(),
            'evidence' => 'Suntem proprietarii bazei. Contract de administrare din 2019.',
            'status' => LocationClaimStatus::Pending,
        ];
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => LocationClaimStatus::Approved,
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'reviewed_by' => $reviewer?->getKey() ?? User::factory(),
        ]);
    }

    public function rejected(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => LocationClaimStatus::Rejected,
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'reviewed_by' => $reviewer?->getKey() ?? User::factory(),
        ]);
    }
}
