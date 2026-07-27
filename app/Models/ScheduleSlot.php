<?php

namespace App\Models;

use App\Enums\Weekday;
use Database\Factories\ScheduleSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a club's weekly schedule: a sport at a location, on a given day
 * and time, for an age group, run by a coach.
 *
 * @property int $id
 * @property int $club_id
 * @property int $club_location_sport_id
 * @property Weekday $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property int|null $age_group_id
 * @property int|null $coach_id
 */
class ScheduleSlot extends Model
{
    /** @use HasFactory<ScheduleSlotFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'club_id',
        'club_location_sport_id',
        'day_of_week',
        'start_time',
        'end_time',
        'age_group_id',
        'coach_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => Weekday::class,
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<ClubLocationSport, $this>
     */
    public function clubLocationSport(): BelongsTo
    {
        return $this->belongsTo(ClubLocationSport::class);
    }

    /**
     * @return BelongsTo<AgeGroup, $this>
     */
    public function ageGroup(): BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }

    /**
     * @return BelongsTo<Coach, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
