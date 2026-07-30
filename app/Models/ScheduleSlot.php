<?php

namespace App\Models;

use App\Enums\Weekday;
use Database\Factories\ScheduleSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a club's weekly schedule: a sport at a location, on a given day
 * and time, for an age group, run by a person.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $organization_location_sport_id
 * @property Weekday $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property int|null $age_group_id
 * @property int|null $person_id
 */
class ScheduleSlot extends Model
{
    /** @use HasFactory<ScheduleSlotFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'organization_location_sport_id',
        'day_of_week',
        'start_time',
        'end_time',
        'age_group_id',
        'person_id',
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
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<OrganizationLocationSport, $this>
     */
    public function organizationLocationSport(): BelongsTo
    {
        return $this->belongsTo(OrganizationLocationSport::class);
    }

    /**
     * @return BelongsTo<AgeGroup, $this>
     */
    public function ageGroup(): BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
