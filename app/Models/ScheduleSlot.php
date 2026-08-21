<?php

namespace App\Models;

use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use Database\Factories\ScheduleSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One weekly interval, of one of two kinds.
 *
 * A `training` belongs to a club's sport at a location, for an age group, run by
 * a person — and may say which space it happens in. An `access` interval is a
 * space's tariff: how you get in, what it costs, and when that applies.
 *
 * A tariff may leave the weekday and the hours empty, which means nobody knows
 * the timetable — the park hoop is free whenever it is light. Only a tariff may:
 * a training with no hour is a session nobody can turn up to.
 *
 * They share a table because they share a shape, which is what lets one query
 * answer "what is happening near me right now" across both.
 *
 * @property int $id
 * @property ScheduleSlotKind $kind
 * @property int|null $organization_id
 * @property int|null $organization_location_sport_id
 * @property int|null $space_id
 * @property Weekday|null $day_of_week
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $price
 * @property SpaceAccessMode|null $access_mode
 * @property PriceUnit|null $price_unit
 * @property string|null $price_notes
 * @property int|null $age_group_id
 * @property int|null $level_id
 * @property int|null $person_id
 * @property-read Space|null $space  a training slot need not happen in a known space
 */
class ScheduleSlot extends Model
{
    /** @use HasFactory<ScheduleSlotFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'kind',
        'organization_id',
        'organization_location_sport_id',
        'space_id',
        'day_of_week',
        'start_time',
        'end_time',
        'price',
        'access_mode',
        'price_unit',
        'price_notes',
        'age_group_id',
        'level_id',
        'person_id',
    ];

    /**
     * Mirrors the column default, so a freshly built slot reports its kind
     * without a round trip to the database.
     *
     * @var array<string, string>
     */
    protected $attributes = ['kind' => ScheduleSlotKind::Training->value];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ScheduleSlotKind::class,
            'day_of_week' => Weekday::class,
            'price' => 'decimal:2',
            'access_mode' => SpaceAccessMode::class,
            'price_unit' => PriceUnit::class,
        ];
    }

    /**
     * Whether this interval names a weekday and a time range.
     *
     * A tariff without them prices a space whose timetable nobody knows, and the
     * page has to say that rather than draw an empty week.
     */
    public function hasHours(): bool
    {
        return $this->day_of_week !== null
            && $this->start_time !== null
            && $this->end_time !== null;
    }

    /**
     * What this interval costs, as a number rather than the decimal string the
     * cast hands back. Null means nobody has said — never that it is free.
     */
    public function effectivePrice(): ?float
    {
        return $this->price === null ? null : (float) $this->price;
    }

    /**
     * The unit that price is in: its own, else whatever the way in implies —
     * an entry for a walk-in, an hour for a booking.
     */
    public function effectivePriceUnit(): ?PriceUnit
    {
        return $this->price_unit ?? $this->access_mode?->defaultPriceUnit();
    }

    /**
     * Each kind needs its own anchor, and the database cannot say so: every one
     * of these columns is nullable because no kind uses them all.
     *
     * A slot with no anchor would be an interval belonging to nothing, invisible
     * everywhere and impossible to explain. A tariff with no way in would price
     * a door nobody was told how to open, and a training with no weekday would
     * be a session nobody can turn up to.
     */
    protected static function booted(): void
    {
        static::saving(function (self $slot): void {
            $missing = match ($slot->kind) {
                ScheduleSlotKind::Training => match (true) {
                    $slot->organization_location_sport_id === null => 'organization_location_sport_id',
                    ! $slot->hasHours() => 'day_of_week, start_time and end_time',
                    default => null,
                },
                ScheduleSlotKind::Access => match (true) {
                    $slot->space_id === null => 'space_id',
                    $slot->access_mode === null => 'access_mode',
                    default => null,
                },
            };

            if ($missing !== null) {
                throw new LogicException(
                    "A {$slot->kind->value} schedule slot requires {$missing}.",
                );
            }
        });
    }

    public function isTraining(): bool
    {
        return $this->kind === ScheduleSlotKind::Training;
    }

    public function isAccess(): bool
    {
        return $this->kind === ScheduleSlotKind::Access;
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
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<AgeGroup, $this>
     */
    public function ageGroup(): BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
