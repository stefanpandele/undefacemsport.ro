<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Enums\Plan;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 1_000_000),
            'company_name' => $name.' SRL',
            'fiscal_code' => 'RO'.fake()->unique()->numberBetween(1_000_000, 99_999_999),
            'is_vat_payer' => fake()->boolean(),
            'address' => fake()->streetAddress(),
            'county' => fake()->randomElement(config('counties')),
            'city' => fake()->city(),
            'plan' => Plan::Free,
            'type' => OrganizationType::Club,
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (): array => ['plan' => Plan::Pro]);
    }

    public function premium(): static
    {
        return $this->state(fn (): array => ['plan' => Plan::Premium]);
    }

    /**
     * A company that operates a sports venue and rents it out.
     */
    public function venue(): static
    {
        return $this->state(fn (): array => ['type' => OrganizationType::Venue]);
    }

    /**
     * A clinic, or a single practitioner working as a PFA — same model, fewer
     * people in it.
     */
    public function practice(): static
    {
        return $this->state(fn (): array => ['type' => OrganizationType::Practice]);
    }
}
