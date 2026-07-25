<?php

namespace Database\Factories;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContactType::Phone,
            'role' => ContactRole::General,
            'value' => fake()->phoneNumber(),
            'name' => null,
            'sort_order' => 0,
        ];
    }

    public function email(): static
    {
        return $this->state(fn (): array => [
            'type' => ContactType::Email,
            'value' => fake()->safeEmail(),
        ]);
    }

    public function website(): static
    {
        return $this->state(fn (): array => [
            'type' => ContactType::Website,
            'value' => fake()->url(),
        ]);
    }
}
