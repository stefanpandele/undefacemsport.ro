<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\ClubLocation;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubLocation>
 */
class ClubLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'location_id' => Location::factory(),
        ];
    }
}
