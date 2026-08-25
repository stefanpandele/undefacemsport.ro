<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use App\Enums\OrganizationType;
use App\Enums\Plan;
use App\Enums\ScheduleSlotKind;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The account holder: a legal entity with a plan, staff and an approval flow.
 * The same thing whether it runs training programmes, rents out pitches, or
 * treats athletes — nothing records which, because `offers()` reads it back from
 * what has been published. One address, `/la/{slug}`, whatever it turns out to
 * be.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $company_name
 * @property string|null $fiscal_code
 * @property bool|null $is_vat_payer
 * @property string|null $address
 * @property string|null $county
 * @property string|null $city
 * @property string|null $description
 * @property string|null $logo_path
 * @property string|null $cover_path
 * @property Plan $plan
 * @property int|null $owner_user_id
 * @property-read string|null $logo_url
 * @property-read string|null $cover_url
 * @property-read int|null $counties_count  only set by an explicit select alias
 * @property-read int|null $courses_count  only set by an explicit withCount alias
 * @property-read int|null $open_access_count  only set by an explicit withCount alias
 * @property-read int|null $rental_count  only set by an explicit withCount alias
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'company_name',
        'fiscal_code',
        'is_vat_payer',
        'address',
        'county',
        'city',
        'description',
        'logo_path',
        'cover_path',
    ];

    /**
     * Mirrors the column default, so a freshly created organization reports its
     * plan without having to be refreshed from the database.
     *
     * @var array<string, string>
     */
    protected $attributes = ['plan' => Plan::Free->value];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_vat_payer' => 'boolean',
            'plan' => Plan::class,
        ];
    }

    /**
     * A slug free to take, derived from the name and falling back to the city
     * before a bare counter — two clubs called "Dinamo" read better as
     * `dinamo-bucuresti` than as `dinamo-2`.
     */
    public static function uniqueSlug(string $name, ?string $city = null): string
    {
        $base = Str::slug($name);

        $candidates = filled($city) ? [$base, $base.'-'.Str::slug($city)] : [$base];

        foreach ($candidates as $candidate) {
            if (! static::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        $suffix = 2;

        while (static::query()->where('slug', $base.'-'.$suffix)->exists()) {
            $suffix++;
        }

        return $base.'-'.$suffix;
    }

    /**
     * Whether this organization publishes the kind of offer that makes it one of
     * these.
     *
     * A pool operator that also runs a swimming club is both, and nobody had to
     * declare it — the training programme and the ticketed hours each speak for
     * themselves. Reading it from the offers is what keeps the answer true: a
     * stored flag survives the deletion of the last space it stood for.
     *
     * A space always carries an access mode, so publishing one *is* the claim
     * that somebody can get in. Moderation gates it, the same as everywhere else
     * a visitor is told something.
     */
    public function offers(OrganizationType $type): bool
    {
        return match ($type) {
            OrganizationType::Club => $this->organizationSports()->exists(),
            OrganizationType::Venue => $this->spaces()->approved()->exists(),
            OrganizationType::Practice => $this->services()->exists(),
        };
    }

    /**
     * @return list<OrganizationType>
     */
    public function offeredTypes(): array
    {
        return array_values(array_filter(
            OrganizationType::cases(),
            fn (OrganizationType $type): bool => $this->offers($type),
        ));
    }

    /**
     * The SQL twin of `offers()`, for the listings that ask the question of
     * thousands of rows at once.
     *
     * The approved check is spelled out rather than calling `Space::approved()`:
     * inside `whereHas` the builder is not typed to a model, so the scope would
     * be invisible to static analysis.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOffering(Builder $query, OrganizationType $type): void
    {
        match ($type) {
            OrganizationType::Club => $query->whereHas('organizationSports'),
            OrganizationType::Venue => $query->whereHas(
                'spaces',
                fn (Builder $spaces) => $spaces->where('status', FacilityStatus::Approved),
            ),
            OrganizationType::Practice => $query->whereHas('services'),
        };
    }

    /**
     * Whether the organization's current plan includes the given feature.
     */
    public function planAllows(string $feature): bool
    {
        return ($this->plan ?? Plan::Free)->allows($feature);
    }

    /**
     * The plan's numeric limit for the given key, or null for unlimited.
     */
    public function planLimit(string $key): ?int
    {
        return ($this->plan ?? Plan::Free)->limit($key);
    }

    /**
     * Whether the club can still add another item under the given plan limit.
     */
    public function withinPlanLimit(string $key, int $current): bool
    {
        $limit = $this->planLimit($key);

        return $limit === null || $current < $limit;
    }

    /**
     * Whether the club can add another sport under its plan's `sports` limit.
     */
    public function canAddSport(): bool
    {
        return $this->withinPlanLimit('sports', $this->organizationSports()->count());
    }

    /**
     * Whether the club can add another location under its plan's `locations` limit.
     */
    public function canAddLocation(): bool
    {
        return $this->withinPlanLimit('locations', $this->organizationLocations()->count());
    }

    /**
     * How many distinct things the organization sells access to, counted as one
     * sport at one address rather than one row per court.
     *
     * Every court is its own row — indoor and outdoor padel are a real choice, so
     * "Teren 6 outdoor" has to be nameable. But eleven courts are eleven bricks
     * of one offer, and charging each against the plan would price the honest
     * page higher than a vague one, then show a visitor five courts out of
     * eleven: not a smaller page, a false one.
     *
     * Only the spaces it operates count — `spaces()` goes through
     * `organization_location`, so a park court declared for everyone's benefit is
     * never charged against the quota either.
     *
     * Counted over a subquery rather than with `count(distinct a, b)`, which
     * MariaDB accepts and SQLite does not.
     */
    public function spaceOfferCount(): int
    {
        $offers = $this->spaces()
            ->select('spaces.location_id', 'spaces.sport_id')
            ->distinct()
            ->toBase();

        return DB::query()->fromSub($offers, 'offers')->count();
    }

    /**
     * Whether the organization can add another space under its plan's `spaces`
     * limit.
     *
     * Given a location and sport it already sells, always: that court is another
     * unit of something already paid for. The arguments are optional because the
     * dashboard asks the question before any of it is known.
     */
    public function canAddSpace(?int $locationId = null, ?int $sportId = null): bool
    {
        if ($locationId !== null && $this->sellsSpaceAt($locationId, $sportId)) {
            return true;
        }

        return $this->withinPlanLimit('spaces', $this->spaceOfferCount());
    }

    /**
     * Whether this sport at this address is already on offer — a null sport being
     * the sauna, which is its own kind of offer.
     */
    private function sellsSpaceAt(int $locationId, ?int $sportId): bool
    {
        return $this->spaces()
            ->where('spaces.location_id', $locationId)
            ->when(
                $sportId === null,
                fn ($spaces) => $spaces->whereNull('spaces.sport_id'),
                fn ($spaces) => $spaces->where('spaces.sport_id', $sportId),
            )
            ->exists();
    }

    /**
     * Whether the organization can add another service under its plan's
     * `services` limit.
     */
    public function canAddService(): bool
    {
        return $this->withinPlanLimit('services', $this->services()->count());
    }

    /**
     * The club's presence at a location with exactly this address, if any —
     * optionally excluding a OrganizationLocation being edited.
     */
    public function organizationLocationAt(?string $county, ?string $city, ?string $address, ?int $excludeId = null): ?OrganizationLocation
    {
        if (blank($county) || blank($city) || blank($address)) {
            return null;
        }

        return $this->organizationLocations()
            ->whereHas('location', fn ($query) => $query
                ->where('county', $county)
                ->where('city', $city)
                ->where('address', $address))
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->first();
    }

    /**
     * Find or create the shared location at this address, record the club's
     * presence there, and sync the sports it teaches. An existing location is
     * reused (its canonical name is preserved) instead of being duplicated.
     *
     * Saying where you teach a sport is how you declare that you teach it, so
     * the `organization_sport` row is created here rather than demanded on a
     * screen beforehand. Rows are only added, never removed: dropping a sport
     * from the last address that had it would take its gallery and its benefits
     * with it, and a club moving halls would lose both.
     *
     * @param  array{county: string, city: string, address: string, name: string, latitude?: float|string|null, longitude?: float|string|null, google_place_id?: string|null}  $attributes
     * @param  array<int>  $sportIds
     * @param  int|null  $knownLocationId  a shared location the caller has confirmed is the right one
     */
    public function syncLocation(
        array $attributes,
        array $sportIds = [],
        ?OrganizationLocation $organizationLocation = null,
        ?int $knownLocationId = null,
    ): OrganizationLocation {
        return DB::transaction(function () use ($attributes, $sportIds, $organizationLocation, $knownLocationId): OrganizationLocation {
            $location = $this->resolveLocation($attributes, $knownLocationId);

            $organizationLocation = $organizationLocation === null
                ? $this->organizationLocations()->firstOrCreate(['location_id' => $location->getKey()])
                : tap($organizationLocation)->update(['location_id' => $location->getKey()]);

            $organizationLocation->sports()->sync($sportIds);
            $this->declareSports($sportIds);

            return $organizationLocation;
        });
    }

    /**
     * Whether anybody here runs one-to-one sessions for a sport.
     *
     * Read from the people rather than ticked on the sport: a club claiming 1:1
     * with nobody behind it who does it is a promise with no name on it, and the
     * page already lists which of its people take clients alone.
     */
    public function offersPrivateSessionsFor(int $sportId): bool
    {
        return $this->people->contains(
            fn (Person $person): bool => $person->offersPrivateSessionsIn($sportId),
        );
    }

    /**
     * Record that this organization teaches each of these sports, keeping the
     * presentation of any it already had.
     *
     * @param  array<int>  $sportIds
     */
    public function declareSports(array $sportIds): void
    {
        $existing = $this->organizationSports()->pluck('sport_id')->all();
        $next = $this->organizationSports()->max('sort_order') ?? -1;

        foreach (array_diff(array_unique($sportIds), $existing) as $sportId) {
            $this->organizationSports()->create([
                'sport_id' => $sportId,
                'sort_order' => ++$next,
            ]);
        }

        $this->unsetRelation('organizationSports');
    }

    /**
     * The shared location a presence belongs to: one the caller explicitly chose,
     * else the place Google's id or the exact address already identifies, else a
     * new record.
     *
     * This is where the truth lives — not in the panel's search button, which a
     * user can skip by filling the address and saving straight through, and which
     * imports and seeders never touch at all.
     *
     * Proximity is deliberately absent: two halls can sit 50m apart, so a near
     * miss is a question for a human, never a silent attachment. The club panel
     * asks it (see LocationResource); everything else gets a new record.
     *
     * @param  array{county: string, city: string, address: string, name: string, latitude?: float|string|null, longitude?: float|string|null, google_place_id?: string|null}  $attributes
     */
    private function resolveLocation(array $attributes, ?int $knownLocationId): Location
    {
        if ($knownLocationId !== null) {
            $known = Location::query()->findOrFail($knownLocationId);

            return $this->applyPenTo($known, $attributes);
        }

        $placeId = $attributes['google_place_id'] ?? null;

        $existing = Location::exactMatch(
            $placeId,
            $attributes['county'],
            $attributes['city'],
            $attributes['address'],
        );

        if ($existing !== null) {
            // Backfill Google's identity onto a place first created without it, so
            // the next club that arrives with a place id matches this row instead
            // of starting a duplicate beside it.
            if (filled($placeId) && blank($existing->google_place_id)) {
                $existing->update(['google_place_id' => $placeId]);
            }

            return $this->applyPenTo($existing, $attributes);
        }

        return Location::create([
            'county' => $attributes['county'],
            'city' => $attributes['city'],
            'address' => $attributes['address'],
            'name' => $attributes['name'],
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'google_place_id' => $placeId,
        ]);
    }

    /**
     * Write the place's own fields, but only when this organization is the one
     * that holds the pen on it.
     *
     * For everybody else the canonical record is read-only: another club must not
     * be able to rename a place a dozen clubs rely on, which is what the
     * correction flow exists for. Once a claim is approved, the same save path
     * quietly becomes an edit.
     *
     * @param  array{county: string, city: string, address: string, name: string, latitude?: float|string|null, longitude?: float|string|null, google_place_id?: string|null}  $attributes
     */
    private function applyPenTo(Location $location, array $attributes): Location
    {
        if (! $location->isClaimedBy($this)) {
            return $location;
        }

        $location->fill([
            'name' => $attributes['name'],
            'county' => $attributes['county'],
            'city' => $attributes['city'],
            'address' => $attributes['address'],
        ]);

        // Renaming has to renew the slug, or the public URL keeps advertising a
        // name the place no longer has. The old one is kept as a redirect, exactly
        // as a merge does — an indexed URL is not the editor's to break.
        if ($location->isDirty('name')) {
            $previous = $location->slug;
            $location->slug = Location::uniqueSlug($attributes['name'], $attributes['city']);

            LocationRedirect::updateOrCreate(
                ['slug' => $previous],
                ['location_id' => $location->getKey()],
            );
        }

        $location->save();

        return $location;
    }

    /**
     * Create a club and make its creator the owner and first member.
     *
     * @param  array{name: string, slug: string}  $attributes
     */
    public static function createForOwner(User $owner, array $attributes): self
    {
        return DB::transaction(function () use ($owner, $attributes): self {
            $organization = static::create($attributes);
            $organization->addMember($owner);

            return $organization->refresh();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class)
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationSport, $this>
     */
    public function organizationSports(): HasMany
    {
        return $this->hasMany(OrganizationSport::class);
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * @return HasMany<ScheduleSlot, $this>
     */
    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    /**
     * Only the sessions the organization teaches.
     *
     * A tariff is credited to whoever wrote it, so a club that rents out its dead
     * hours owns intervals that are not trainings and have no club sport behind
     * them. Anything drawing a timetable wants this relation, not the one above.
     *
     * @return HasMany<ScheduleSlot, $this>
     */
    public function trainingSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class)
            ->where('kind', ScheduleSlotKind::Training);
    }

    /**
     * @return HasMany<OrganizationLocation, $this>
     */
    public function organizationLocations(): HasMany
    {
        return $this->hasMany(OrganizationLocation::class);
    }

    /**
     * What a practice sells: consultations, sessions, assessments.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order');
    }

    /**
     * Every space this organization operates, across all its locations.
     *
     * @return HasManyThrough<Space, OrganizationLocation, $this>
     */
    public function spaces(): HasManyThrough
    {
        return $this->hasManyThrough(Space::class, OrganizationLocation::class);
    }

    /**
     * The shared locations where this club operates.
     *
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        // The pivot table has to be named explicitly. Eloquent would derive it
        // alphabetically from the model names — `location_organization` — but the
        // table is `organization_location`, which reads as what it is: an
        // organization's presence at a place.
        return $this->belongsToMany(Location::class, 'organization_location');
    }

    /**
     * @return MorphMany<Image, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * @return MorphMany<Contact, $this>
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function addMember(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /** @var self $organization */
            $organization = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $organization->users()->syncWithoutDetaching($user);

            if (is_null($organization->owner_user_id)) {
                $organization->owner()->associate($user);
                $organization->save();

                // The row above is a second instance, locked for the update. The
                // caller is still holding this one, and would go on believing the
                // organization has no owner.
                $this->owner_user_id = $organization->owner_user_id;
                $this->syncOriginalAttribute('owner_user_id');
            }
        });
    }

    /**
     * Hand the club over to another user, who becomes the new owner (master).
     * The previous owner remains a regular member.
     */
    public function transferOwnershipTo(User $newOwner): void
    {
        DB::transaction(function () use ($newOwner): void {
            /** @var self $organization */
            $organization = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $organization->users()->syncWithoutDetaching($newOwner);
            $organization->owner()->associate($newOwner)->save();
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->urlForPath($this->logo_path);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->urlForPath($this->cover_path);
    }

    private function urlForPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return Storage::disk('s3')->url($path);
    }
}
