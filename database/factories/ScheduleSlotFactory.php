<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubLocationSport;
use App\Models\ScheduleSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleSlot>
 */
class ScheduleSlotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'club_location_sport_id' => ClubLocationSport::factory(),
            'day_of_week' => Weekday::Monday,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'age_group_id' => AgeGroup::factory(),
            'coach_id' => null,
        ];
    }
}
