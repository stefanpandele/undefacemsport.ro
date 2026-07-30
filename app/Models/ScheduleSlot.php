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
 * a person — and may say which space it happens in. An `access` interval belongs
 * to a space and means you may turn up then, optionally at its own price.
 *
 * They share a table because they share a shape, which is what lets one query
 * answer "what is happening near me right now" across both.
 *
 * @property int $id
 * @property ScheduleSlotKind $kind
 * @property int|null $organization_id
 * @property int|null $organization_location_sport_id
 * @property int|null $space_id
 * @property Weekday $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property string|null $price
 * @property SpaceAccessMode|null $access_mode
 * @property PriceUnit|null $price_unit
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
     * How you get in during this interval — its own answer, or the space's.
     */
    public function accessMode(): ?SpaceAccessMode
    {
        return $this->access_mode ?? $this->space?->access_mode;
    }

    /**
     * What this interval costs.
     *
     * A price is only inherited from the space when the way in is the same. An
     * open-gym evening in a hall that is otherwise booked by the hour must not
     * quietly borrow the hourly rate — 25 lei a head and 180 lei an hour are not
     * the same number wearing a different label.
     */
    public function effectivePrice(): ?float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        $space = $this->space;

        if ($space === null) {
            return null;
        }

        if ($this->access_mode !== null && $this->access_mode !== $space->access_mode) {
            return null;
        }

        return $space->price === null ? null : (float) $space->price;
    }

    /**
     * The unit that price is in: its own, the space's when the way in matches,
     * else whatever the mode implies.
     */
    public function effectivePriceUnit(): ?PriceUnit
    {
        if ($this->price_unit !== null) {
            return $this->price_unit;
        }

        $mode = $this->accessMode();
        $space = $this->space;

        if ($space === null) {
            return $mode?->defaultPriceUnit();
        }

        if ($this->access_mode === null || $this->access_mode === $space->access_mode) {
            return $space->price_unit ?? $mode?->defaultPriceUnit();
        }

        return $mode?->defaultPriceUnit();
    }

    /**
     * Each kind needs its own anchor, and the database cannot say so: both
     * columns are nullable because neither kind uses both. A slot with neither
     * would be an interval belonging to nothing, invisible everywhere and
     * impossible to explain.
     */
    protected static function booted(): void
    {
        static::saving(function (self $slot): void {
            $missing = match ($slot->kind) {
                ScheduleSlotKind::Training => $slot->organization_location_sport_id === null
                    ? 'organization_location_sport_id'
                    : null,
                ScheduleSlotKind::Access => $slot->space_id === null ? 'space_id' : null,
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
