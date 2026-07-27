<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
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
}
