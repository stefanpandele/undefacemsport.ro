<?php

namespace Database\Factories;

use App\Models\ClubLocation;
use App\Models\ClubLocationSport;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubLocationSport>
 */
class ClubLocationSportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_location_id' => ClubLocation::factory(),
            'sport_id' => Sport::factory(),
        ];
    }
}
