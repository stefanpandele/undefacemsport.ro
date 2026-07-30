<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use Carbon\CarbonInterface;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something you can actually use at a location: a pool, a pitch, a court, a gym,
 * a sauna.
 *
 * A space exists whether or not anybody sells access to it — the hoop in a park
 * is here with `organization_location_id` null. When a company starts renting it
 * out, only that column changes; no data moves.
 *
 * @property int $id
 * @property int $location_id
 * @property int|null $organization_location_id
 * @property string $name
 * @property int|null $sport_id
 * @property SpaceAccessMode $access_mode
 * @property string|null $price
 * @property PriceUnit|null $price_unit
 * @property string|null $price_notes
 * @property int|null $capacity
 * @property bool|null $is_indoor
 * @property bool|null $has_floodlights
 * @property string|null $surface
 * @property FacilityStatus $status
 * @property Carbon|null $last_verified_at
 * @property int $sort_order
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
        'access_mode',
        'price',
        'price_unit',
        'price_notes',
        'capacity',
        'is_indoor',
        'has_floodlights',
        'surface',
        'sort_order',
    ];

    /**
     * Mirrors the column defaults, so a freshly built space reports its status
     * and access mode without a round trip to the database.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => FacilityStatus::Approved->value,
        'access_mode' => SpaceAccessMode::OpenAccess->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_mode' => SpaceAccessMode::class,
            'price_unit' => PriceUnit::class,
            'status' => FacilityStatus::class,
            'price' => 'decimal:2',
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
     * @param  Builder<$this>  $query
     */
    public function scopeOfMode(Builder $query, SpaceAccessMode $mode): void
    {
        $query->where('access_mode', $mode);
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
     * An unpriced space is unknown, and the page says so rather than promising
     * a visitor it is free.
     */
    public function isFree(): bool
    {
        return $this->price !== null && (float) $this->price === 0.0;
    }

    /**
     * The lowest price anyone pays here — the base price, undercut by any cheaper
     * interval. This is why the chooser reads "de la 35 lei" instead of quoting
     * an afternoon rate that is wrong all morning.
     */
    public function priceFrom(): ?float
    {
        $prices = $this->accessSlots
            ->pluck('price')
            ->push($this->price)
            ->filter(fn (mixed $price): bool => $price !== null)
            ->map(fn (mixed $price): float => (float) $price);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    /**
     * The price as a visitor reads it, or null when nobody has said.
     */
    public function priceFromLabel(): ?string
    {
        $from = $this->priceFrom();

        if ($from === null) {
            return null;
        }

        if ($from === 0.0) {
            return 'Gratuit';
        }

        $unit = $this->price_unit ?? $this->access_mode->defaultPriceUnit();
        $label = $unit->format($from);

        // "de la" only earns its place when the price actually varies.
        return $this->hasVaryingPrice() ? 'de la '.$label : $label;
    }

    public function hasVaryingPrice(): bool
    {
        $distinct = $this->accessSlots
            ->pluck('price')
            ->push($this->price)
            ->filter(fn (mixed $price): bool => $price !== null)
            ->map(fn (mixed $price): float => (float) $price)
            ->unique();

        return $distinct->count() > 1;
    }

    /**
     * Opening hours grouped by weekday, each day's intervals in order. Days with
     * no interval are absent — the space is closed then.
     *
     * @return array<int, list<array{start: string, end: string, price: float|null}>>
     */
    public function hoursByDay(): array
    {
        return $this->accessSlots
            ->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']])
            ->groupBy(fn (ScheduleSlot $slot): int => $slot->day_of_week->value)
            ->map(fn (Collection $slots): array => array_values($slots
                ->map(fn (ScheduleSlot $slot): array => [
                    'start' => substr((string) $slot->start_time, 0, 5),
                    'end' => substr((string) $slot->end_time, 0, 5),
                    'price' => $slot->price === null ? null : (float) $slot->price,
                ])
                ->all()))
            ->all();
    }

    /**
     * Whether the space is open at a given moment. Uses the same weekday-plus-
     * time-range comparison as `ExploreController::liveLocationIds()`.
     */
    public function openAt(?CarbonInterface $moment = null): bool
    {
        $moment ??= Carbon::now();
        $day = Weekday::fromDate($moment)->value;
        $time = $moment->format('H:i:s');

        return $this->accessSlots->contains(
            fn (ScheduleSlot $slot): bool => $slot->day_of_week->value === $day
                && $slot->start_time <= $time
                && $slot->end_time >= $time,
        );
    }

    /**
     * When the space closes today, if it is open now.
     */
    public function closesAt(?CarbonInterface $moment = null): ?string
    {
        $moment ??= Carbon::now();
        $day = Weekday::fromDate($moment)->value;
        $time = $moment->format('H:i:s');

        $slot = $this->accessSlots
            ->filter(fn (ScheduleSlot $slot): bool => $slot->day_of_week->value === $day
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
