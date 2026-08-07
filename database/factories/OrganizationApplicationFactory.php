<?php

namespace Database\Factories;

use App\Enums\OrganizationApplicationStatus;
use App\Enums\OrganizationType;
use App\Models\OrganizationApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationApplication>
 */
class OrganizationApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'name' => $company,
            'type' => fake()->randomElement(OrganizationType::cases()),
            'company_name' => $company.' SRL',
            'fiscal_code' => 'RO'.fake()->unique()->numberBetween(1_000_000, 99_999_999),
            'is_vat_payer' => fake()->boolean(),
            'address' => fake()->streetAddress(),
            'county' => fake()->randomElement(config('counties')),
            'city' => fake()->city(),
            'contact_name' => fake()->name(),
            'contact_role' => fake()->randomElement(['Președinte', 'Antrenor principal', 'Secretar', 'Administrator', 'Manager']),
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'status' => OrganizationApplicationStatus::Pending,
        ];
    }

    public function ofType(OrganizationType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationApplicationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationApplicationStatus::Rejected,
            'rejection_reason' => 'CUI-ul nu corespunde cu denumirea din cerere.',
            'reviewed_at' => now(),
        ]);
    }
}
