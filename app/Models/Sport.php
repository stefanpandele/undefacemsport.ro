<?php

namespace App\Models;

use Database\Factories\SportFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $color
 * @property-read string $translated_name
 */
class Sport extends Model
{
    /** @use HasFactory<SportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'icon', 'color'];

    /**
     * Sports that are actually **taught** somewhere, biggest first, each with how
     * far it reaches: locations, clubs and cities. Shared by the homepage and
     * the sports index so the two can never disagree about what is popular.
     *
     * Only what is taught. The count reaches the sport through
     * `organization_location_sport`, so a padel court you can rent never lands in
     * it — counting one would make "12 cluburi" a lie on the homepage.
     *
     * @param  string|null  $county  narrow every count to a single county
     * @return list<array{key: string, label: string, icon: string, color: string|null, locationCount: int, clubCount: int, cityCount: int}>
     */
    public static function withReach(?string $county = null): array
    {
        $stats = DB::table('organization_location_sport')
            ->join('organization_location', 'organization_location.id', '=', 'organization_location_sport.organization_location_id')
            ->join('locations', 'locations.id', '=', 'organization_location.location_id')
            ->when($county, fn ($query) => $query->where('locations.county', $county))
            ->groupBy('organization_location_sport.sport_id')
            ->select(['organization_location_sport.sport_id'])
            ->selectRaw('count(distinct locations.id) as location_count')
            ->selectRaw('count(distinct organization_location.organization_id) as club_count')
            ->selectRaw('count(distinct locations.city) as city_count')
            ->get()
            ->keyBy('sport_id');

        if ($stats->isEmpty()) {
            return [];
        }

        return array_values(static::query()
            ->whereIn('id', $stats->keys())
            ->get()
            ->sortByDesc(fn (self $sport): int => (int) $stats[$sport->getKey()]->location_count)
            ->map(fn (self $sport): array => [
                'key' => $sport->slug,
                'label' => $sport->translated_name,
                'icon' => (string) $sport->icon,
                'color' => $sport->color,
                'locationCount' => (int) $stats[$sport->getKey()]->location_count,
                'clubCount' => (int) $stats[$sport->getKey()]->club_count,
                'cityCount' => (int) $stats[$sport->getKey()]->city_count,
            ])
            ->all());
    }

    /**
     * The sport's name in the current locale (from lang/{locale}/sports.php,
     * keyed by slug), falling back to the stored `name`.
     *
     * @return Attribute<string, never>
     */
    protected function translatedName(): Attribute
    {
        return Attribute::get(function (): string {
            $key = 'sports.'.$this->slug;

            return Lang::has($key) ? __($key) : $this->name;
        });
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['cover_path', 'description', 'sort_order'])
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
