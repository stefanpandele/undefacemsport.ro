<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A physical place, shared across clubs: many clubs can operate here, each with
 * its own sports (see OrganizationLocation).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $county
 * @property string|null $city
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $google_place_id
 * @property-read int|null $club_count  only set by an explicit withCount alias
 * @property-read int|null $facility_count  only set by an explicit withCount alias
 * @property float|null $distance_meters a runtime value, only set by Location::nearby()
 * @property-read FacilityLocation|null $pivot  only set when hydrated through Facility::locations()
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * How far apart two records may sit and still be worth asking a human about.
     * A school gym and a private base can be 50m apart, and one complex can have
     * entrances 150m apart — so this radius only ever raises a question, it never
     * attaches anything on its own.
     */
    public const NEARBY_METERS = 200;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'county', 'city', 'address', 'latitude', 'longitude', 'location', 'google_place_id'];

    /** @var list<string> */
    protected $appends = ['location'];

    protected static function booted(): void
    {
        static::creating(function (self $location): void {
            $location->slug ??= static::uniqueSlug($location->name, $location->city);
        });
    }

    /**
     * A URL key for the location, disambiguated by city when two places share
     * a name, then by a counter.
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * The shared location at exactly this address (county + city + address).
     */
    public static function atAddress(?string $county, ?string $city, ?string $address): ?self
    {
        if (blank($county) || blank($city) || blank($address)) {
            return null;
        }

        return static::query()
            ->where('county', $county)
            ->where('city', $city)
            ->where('address', $address)
            ->first();
    }

    /**
     * The location this address is definitely the same as: Google's place id
     * first, then a character-for-character address match.
     *
     * "Definitely" is the point — both signals are safe to act on without asking,
     * which is why proximity is not among them. See `nearby()`.
     */
    public static function exactMatch(?string $placeId, ?string $county, ?string $city, ?string $address): ?self
    {
        if (filled($placeId)) {
            $byPlaceId = static::query()->where('google_place_id', $placeId)->first();

            if ($byPlaceId !== null) {
                return $byPlaceId;
            }
        }

        return static::atAddress($county, $city, $address);
    }

    /**
     * Locations close enough to this point to be the same place, nearest first,
     * each carrying its `distance_meters`.
     *
     * The SQL half is a bounding box — plain arithmetic over the (latitude,
     * longitude) index, so it stays portable down to SQLite, which has no trig
     * functions. The exact circle is then cut in PHP over the handful of rows the
     * box returned.
     *
     * @return Collection<int, static>
     */
    public static function nearby(
        float $latitude,
        float $longitude,
        int $meters = self::NEARBY_METERS,
        ?int $excludeId = null,
    ): Collection {
        $latitudeDelta = $meters / 111_320;
        $longitudeDelta = $meters / max(111_320 * cos(deg2rad($latitude)), 1e-6);

        return static::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->when($excludeId, fn (Builder $query) => $query->whereKeyNot($excludeId))
            ->get()
            ->each(fn (self $location) => $location->distance_meters = static::distanceInMeters(
                $latitude,
                $longitude,
                (float) $location->latitude,
                (float) $location->longitude,
            ))
            ->filter(fn (self $location): bool => $location->distance_meters <= $meters)
            ->sortBy('distance_meters')
            ->values();
    }

    /**
     * Great-circle distance between two points, in metres.
     */
    public static function distanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6_371_000;

        $latitudeDelta = deg2rad($lat2 - $lat1);
        $longitudeDelta = deg2rad($lng2 - $lng1);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * Computed "location" point used by Filament Google Maps (maps the separate
     * latitude/longitude columns to a Google-style {lat,lng} array).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function getLocationAttribute(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }

    /**
     * @param  array{lat: float|string, lng: float|string}|null  $location
     */
    public function setLocationAttribute(?array $location): void
    {
        if (is_array($location)) {
            $this->attributes['latitude'] = $location['lat'];
            $this->attributes['longitude'] = $location['lng'];
            unset($this->attributes['location']);
        }
    }

    /**
     * @return array{lat: string, lng: string}
     */
    public static function getLatLngAttributes(): array
    {
        return ['lat' => 'latitude', 'lng' => 'longitude'];
    }

    public static function getComputedLocation(): string
    {
        return 'location';
    }

    /**
     * @return HasMany<OrganizationLocation, $this>
     */
    public function organizationLocations(): HasMany
    {
        return $this->hasMany(OrganizationLocation::class);
    }

    /**
     * Fixes proposed by clubs that cannot edit this shared place themselves.
     *
     * @return HasMany<LocationCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(LocationCorrection::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        // Named explicitly: Eloquent's alphabetical guess would be
        // `location_organization`, but the table is `organization_location`.
        return $this->belongsToMany(Organization::class, 'organization_location');
    }

    /**
     * Amenities offered at this physical location (shared across organizations).
     *
     * @return BelongsToMany<Facility, $this, FacilityLocation>
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class)
            ->using(FacilityLocation::class)
            ->withPivot('photo_path');
    }
}
