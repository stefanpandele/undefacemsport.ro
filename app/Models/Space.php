<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use Carbon\CarbonInterface;
use Database\Factories\SpaceFactory;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Something you can actually use at a location: a pool, a pitch, a court, a gym,
 * a sauna.
 *
 * A space exists whether or not anybody sells access to it — the hoop in a park
 * is here with `organization_location_id` null. When a company starts renting it
 * out, only that column changes; no data moves.
 *
 * It is a physical thing and nothing more. How you get in and what it costs live
 * on its tariffs — `accessSlots` — because one room can be sold two ways, and a
 * price that is written twice is a price that can disagree with itself.
 *
 * @property int $id
 * @property int $location_id
 * @property int|null $organization_location_id
 * @property string $name
 * @property int|null $sport_id
 * @property int|null $capacity
 * @property bool|null $is_indoor
 * @property bool|null $has_floodlights
 * @property string|null $surface
 * @property FacilityStatus $status
 * @property Carbon|null $last_verified_at
 * @property int $sort_order
 * @property-read Sport|null $sport  a sauna is not a sport
 * @property-read OrganizationLocation|null $organizationLocation  null when nobody operates it
 */
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    /**
     * How long an unmanaged space's information is trusted before it goes back
     * into the admin's queue. Nobody has an incentive to keep a park court's
     * record true, so the platform has to ask itself.
     */
    public const STALE_AFTER_MONTHS = 6;

    /**
     * Space attributes that also exist as a location amenity, and so have to be
     * kept from contradicting it: a base whose pitch has floodlights must not be
     * filtered out of `/explorare` by someone ticking "Iluminat nocturn".
     *
     * Deliberately short. The amenity vocabulary and the things you can book at a
     * location barely overlap — an amenity comes free with being there, a space is
     * paid for separately — so this is a list of exceptions, not a mapping layer.
     * `Încălzire` and `Aer condiționat` will join it when they move from the
     * location to the space.
     *
     * @var array<string, string>
     */
    private const IMPLIED_FACILITIES = ['has_floodlights' => 'Iluminat nocturn'];

    protected static function booted(): void
    {
        static::saved(function (self $space): void {
            $space->declareImpliedFacilities();
        });
    }

    /**
     * Attach the amenities this space's own attributes prove exist here. Only ever
     * adds — the amenities of a shared place are never removed on somebody else's
     * behalf.
     */
    public function declareImpliedFacilities(): void
    {
        $names = collect(self::IMPLIED_FACILITIES)
            ->filter(fn (string $facility, string $attribute): bool => (bool) $this->{$attribute})
            ->values();

        if ($names->isEmpty()) {
            return;
        }

        $ids = Facility::query()->approved()->whereIn('name', $names)->pluck('id');

        if ($ids->isNotEmpty()) {
            $this->location?->facilities()->syncWithoutDetaching($ids->all());
        }
    }

    /** @var list<string> */
    protected $fillable = [
        'location_id',
        'organization_location_id',
        'name',
        'sport_id',
        'capacity',
        'is_indoor',
        'has_floodlights',
        'surface',
        'sort_order',
    ];

    /**
     * Mirrors the column default, so a freshly built space reports its status
     * without a round trip to the database.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => FacilityStatus::Approved->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FacilityStatus::class,
            'capacity' => 'integer',
            'is_indoor' => 'boolean',
            'has_floodlights' => 'boolean',
            'sort_order' => 'integer',
            'last_verified_at' => 'datetime',
        ];
    }

    /**
     * Only spaces cleared for public pages.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', FacilityStatus::Approved);
    }

    /**
     * Spaces somebody operates and charges for.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeManaged(Builder $query): void
    {
        $query->whereNotNull('organization_location_id');
    }

    /**
     * Spaces nobody operates — a park court, a school pitch left open.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeUnmanaged(Builder $query): void
    {
        $query->whereNull('organization_location_id');
    }

    /**
     * Unmanaged spaces whose information is old enough to be worth re-checking,
     * never verified included.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $query->unmanaged()->where(function (Builder $query): void {
            $query->whereNull('last_verified_at')
                ->orWhere('last_verified_at', '<', now()->subMonths(self::STALE_AFTER_MONTHS));
        });
    }

    /**
     * Spaces you can get into this way, according to their tariffs.
     *
     * The SQL twin of `accessModes()`. One condition rather than the two it used
     * to take: the hall booked by the hour all week that runs open-gym on Friday
     * evenings is found by its Friday tariff, like anything else.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOffering(Builder $query, SpaceAccessMode $mode): void
    {
        $query->whereHas('scheduleSlots', fn (BuilderContract $slots) => $slots
            ->where('kind', ScheduleSlotKind::Access)
            ->where('access_mode', $mode));
    }

    public function isManaged(): bool
    {
        return $this->organization_location_id !== null;
    }

    public function isPending(): bool
    {
        return $this->status === FacilityStatus::Pending;
    }

    /**
     * Free means somebody said it costs nothing, not that nobody said anything.
     * A space with no tariff, or a tariff with no price, is unknown — and the
     * page says so rather than promising a visitor it is free.
     */
    public function isFree(?SpaceAccessMode $mode = null): bool
    {
        return $this->priceFrom($mode) === 0.0;
    }

    /**
     * Every way into this space, in the order its tariffs were written.
     *
     * Usually one. A municipal sports hall is the exception that earns the list:
     * open-gym on Friday evenings, booked whole by the hour the rest of the week.
     * One hall, two ways in — so the page offers two, from one record.
     *
     * @return SupportCollection<int, SpaceAccessMode>
     */
    public function accessModes(): SupportCollection
    {
        return collect($this->accessSlots->all())
            ->map(fn (ScheduleSlot $slot): ?SpaceAccessMode => $slot->access_mode)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * The intervals you can get in through a given way, or all of them.
     *
     * @return Collection<int, ScheduleSlot>
     */
    public function slotsFor(?SpaceAccessMode $mode = null): Collection
    {
        if ($mode === null) {
            return $this->accessSlots;
        }

        return $this->accessSlots
            ->filter(fn (ScheduleSlot $slot): bool => $slot->access_mode === $mode)
            ->values();
    }

    /**
     * Whether anybody has said when this space is open.
     *
     * A tariff may carry a price and no hours — a park court is free whenever it
     * is light, and nobody keeps a timetable for it. The page has to tell that
     * apart from a week of closed days, which is a claim nobody made.
     */
    public function hasKnownHours(?SpaceAccessMode $mode = null): bool
    {
        return $this->timedSlots($mode)->isNotEmpty();
    }

    /**
     * The tariffs that actually name a weekday and an interval.
     *
     * @return Collection<int, ScheduleSlot>
     */
    private function timedSlots(?SpaceAccessMode $mode = null): Collection
    {
        return $this->slotsFor($mode)
            ->filter(fn (ScheduleSlot $slot): bool => $slot->hasHours())
            ->values();
    }

    /**
     * The lowest price anyone pays to get in this way — the base price, undercut
     * by any cheaper interval. This is why the chooser reads "de la 35 lei"
     * instead of quoting an afternoon rate that is wrong all morning.
     */
    public function priceFrom(?SpaceAccessMode $mode = null): ?float
    {
        $prices = $this->pricesFor($mode);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    /**
     * The price as a visitor reads it, or null when nobody has said.
     */
    public function priceFromLabel(?SpaceAccessMode $mode = null): ?string
    {
        $from = $this->priceFrom($mode);

        if ($from === null) {
            return null;
        }

        if ($from === 0.0) {
            return 'Gratuit';
        }

        $label = $this->priceUnitFor($mode)->format($from);

        // "de la" only earns its place when the price actually varies.
        return $this->hasVaryingPrice($mode) ? 'de la '.$label : $label;
    }

    public function hasVaryingPrice(?SpaceAccessMode $mode = null): bool
    {
        return $this->pricesFor($mode)->unique()->count() > 1;
    }

    /**
     * Every price on offer for a given way in, one per tariff. Asked for one way
     * in, the other way's tariffs are not candidates — an hourly rate is never
     * the cheapest entry ticket.
     *
     * @return SupportCollection<int, float>
     */
    private function pricesFor(?SpaceAccessMode $mode): SupportCollection
    {
        return collect($this->slotsFor($mode)->all())
            ->map(fn (ScheduleSlot $slot): ?float => $slot->effectivePrice())
            ->filter(fn (?float $price): bool => $price !== null)
            ->values();
    }

    /**
     * What the price for this way in is measured in: whatever its tariffs say,
     * else what the mode implies.
     */
    private function priceUnitFor(?SpaceAccessMode $mode): PriceUnit
    {
        $fromSlot = $this->slotsFor($mode)
            ->map(fn (ScheduleSlot $slot): ?PriceUnit => $slot->effectivePriceUnit())
            ->filter()
            ->first();

        if ($fromSlot instanceof PriceUnit) {
            return $fromSlot;
        }

        return ($mode ?? $this->accessModes()->first() ?? SpaceAccessMode::OpenAccess)->defaultPriceUnit();
    }

    /**
     * The note printed under the price — "Abonament 380 lei / 10 intrări" — for
     * one way in. Belongs to a tariff, so asking for the rental price never
     * surfaces the season ticket that only applies to walk-ins.
     */
    public function priceNotesFor(?SpaceAccessMode $mode = null): ?string
    {
        return $this->slotsFor($mode)
            ->map(fn (ScheduleSlot $slot): ?string => $slot->price_notes)
            ->filter()
            ->first();
    }

    /**
     * Opening hours grouped by weekday, each day's intervals in order. Days with
     * no interval are absent — the space is closed then.
     *
     * @return array<int, list<array{start: string, end: string, price: float|null}>>
     */
    public function hoursByDay(?SpaceAccessMode $mode = null): array
    {
        return $this->timedSlots($mode)
            ->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']])
            ->groupBy(fn (ScheduleSlot $slot): int => (int) $slot->day_of_week?->value)
            ->map(fn (Collection $slots): array => array_values($slots
                ->map(fn (ScheduleSlot $slot): array => [
                    'start' => substr((string) $slot->start_time, 0, 5),
                    'end' => substr((string) $slot->end_time, 0, 5),
                    'price' => $slot->effectivePrice(),
                ])
                ->all()))
            ->all();
    }

    /**
     * Whether the space is open at a given moment. Uses the same weekday-plus-
     * time-range comparison as `ExploreController::liveLocationIds()`.
     */
    public function openAt(?CarbonInterface $moment = null, ?SpaceAccessMode $mode = null): bool
    {
        $moment ??= Carbon::now();
        $day = Weekday::fromDate($moment)->value;
        $time = $moment->format('H:i:s');

        return $this->timedSlots($mode)->contains(
            fn (ScheduleSlot $slot): bool => $slot->day_of_week?->value === $day
                && $slot->start_time <= $time
                && $slot->end_time >= $time,
        );
    }

    /**
     * When the space closes today, if it is open now.
     */
    public function closesAt(?CarbonInterface $moment = null, ?SpaceAccessMode $mode = null): ?string
    {
        $moment ??= Carbon::now();
        $day = Weekday::fromDate($moment)->value;
        $time = $moment->format('H:i:s');

        $slot = $this->timedSlots($mode)
            ->filter(fn (ScheduleSlot $slot): bool => $slot->day_of_week?->value === $day
                && $slot->start_time <= $time
                && $slot->end_time >= $time)
            ->sortBy('end_time')
            ->last();

        return $slot === null ? null : substr((string) $slot->end_time, 0, 5);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The operator's presence at this location, when somebody operates it.
     *
     * @return BelongsTo<OrganizationLocation, $this>
     */
    public function organizationLocation(): BelongsTo
    {
        return $this->belongsTo(OrganizationLocation::class);
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Every slot that happens in this space, of either kind.
     *
     * @return HasMany<ScheduleSlot, $this>
     */
    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    /**
     * This space's own opening hours — not the trainings that happen inside it.
     *
     * @return HasMany<ScheduleSlot, $this>
     */
    public function accessSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class)
            ->where('kind', ScheduleSlotKind::Access)
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }
}
