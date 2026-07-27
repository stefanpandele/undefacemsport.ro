<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A physical place, shared across clubs: many clubs can operate here, each with
 * its own sports (see ClubLocation).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $county
 * @property string|null $city
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'county', 'city', 'address', 'latitude', 'longitude', 'location'];

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
     * @return HasMany<ClubLocation, $this>
     */
    public function clubLocations(): HasMany
    {
        return $this->hasMany(ClubLocation::class);
    }

    /**
     * @return BelongsToMany<Club, $this>
     */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class);
    }

    /**
     * Amenities offered at this physical location (shared across clubs).
     *
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class);
    }
}
