<?php

namespace App\Http\Controllers;

use App\Enums\FacilityStatus;
use App\Enums\LocationWay;
use App\Enums\OrganizationType;
use App\Enums\Weekday;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Sport;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExploreController extends Controller
{
    /**
     * Browse a city's locations and the sports played in them.
     *
     * Without a city the page is a city picker instead: guessing one for the
     * visitor used to land someone from Cluj in București without saying so.
     * Filters only appear once a city is chosen — they are meaningless before.
     */
    public function index(Request $request): Response
    {
        $sport = $request->string('sport')->trim()->toString() ?: null;
        // Arriving from the sports index, the picker only offers cities where
        // that sport is actually taught — sending someone to a city that has
        // none of it would be a dead end dressed up as a choice.
        $cities = $this->cities($sport);
        $city = $this->resolveCity($request->string('oras')->trim()->toString(), $cities);
        $facilities = array_values($request->collect('facilitati')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->all());
        $search = $request->string('cauta')->trim()->toString() ?: null;
        // Which way in the visitor is after: an organised programme, walking in,
        // or booking the whole space. Unknown values are ignored rather than
        // returning nothing, so a stale link still lands somewhere useful.
        $way = LocationWay::tryFrom($request->string('mod')->trim()->toString());

        return Inertia::render('public/explore/Index', [
            'city' => $city,
            'cities' => $cities,
            'filters' => [
                'sport' => $sport,
                'facilities' => $facilities,
                'search' => $search,
                'way' => $way?->value,
            ],
            'ways' => LocationWay::options(),
            'locations' => $city === null ? [] : $this->locations($city, $sport, $facilities, $search, $way),
            'sports' => $city === null ? [] : $this->sports($city),
            'facilities' => $city === null ? [] : $this->facilities($city),
        ]);
    }

    /**
     * Every city that has a location, with enough substance to be worth a card:
     * how much there is to find, the colour of its biggest sport, and a centre
     * point so a visitor who does share their position can be dropped into the
     * nearest city.
     *
     * Busiest first, so the cities most people are looking for need no reading.
     *
     * @param  string|null  $sport  keep only the cities where this sport is taught
     * @return list<array{name: string, locationCount: int, clubCount: int, sportCount: int, color: string|null, lat: float|null, lng: float|null}>
     */
    private function cities(?string $sport = null): array
    {
        $topSports = $this->topSportsByCity();

        return array_values(DB::table('locations')
            ->leftJoin('organization_location', 'organization_location.location_id', '=', 'locations.id')
            // Left-joined with the type in the ON clause, not in a WHERE: a city
            // whose locations host no club at all must still get a card, with a
            // count of zero, rather than disappearing from the picker.
            ->leftJoin('organizations', fn (JoinClause $join) => $join
                ->on('organizations.id', '=', 'organization_location.organization_id')
                ->where('organizations.type', OrganizationType::Club->value))
            ->whereNotNull('locations.city')
            ->when($sport, fn (QueryBuilder $query) => $query->whereExists(
                fn (QueryBuilder $exists) => $exists->from('organization_location as cl')
                    ->join('organization_location_sport as cls', 'cls.organization_location_id', '=', 'cl.id')
                    ->join('organizations as o', 'o.id', '=', 'cl.organization_id')
                    ->join('sports', 'sports.id', '=', 'cls.sport_id')
                    ->whereColumn('cl.location_id', 'locations.id')
                    ->where('o.type', OrganizationType::Club->value)
                    ->where('sports.slug', $sport),
            ))
            ->groupBy('locations.city')
            ->select(['locations.city'])
            ->selectRaw('count(distinct locations.id) as location_count')
            // Counting the joined organizations, so only clubs land in the total.
            ->selectRaw('count(distinct organizations.id) as club_count')
            ->selectRaw('avg(locations.latitude) as lat')
            ->selectRaw('avg(locations.longitude) as lng')
            ->orderByDesc('location_count')
            ->orderBy('locations.city')
            ->get()
            ->map(function (object $row) use ($topSports): array {
                $sports = $topSports->get($row->city, collect());
                $color = $sports->first()->color ?? null;

                return [
                    'name' => (string) $row->city,
                    'locationCount' => (int) $row->location_count,
                    'clubCount' => (int) $row->club_count,
                    'sportCount' => $sports->count(),
                    'color' => $color === null ? null : (string) $color,
                    'lat' => $row->lat === null ? null : (float) $row->lat,
                    'lng' => $row->lng === null ? null : (float) $row->lng,
                ];
            })
            ->all());
    }

    /**
     * The sports of each city, most widespread first. One row per sport, so the
     * card gets both its colour (the biggest sport) and how many sports there
     * are to choose from.
     *
     * @return Collection<int|string, Collection<int, \stdClass>>
     */
    private function topSportsByCity(): Collection
    {
        return DB::table('organization_location_sport')
            ->join('organization_location', 'organization_location.id', '=', 'organization_location_sport.organization_location_id')
            ->join('organizations', 'organizations.id', '=', 'organization_location.organization_id')
            ->join('locations', 'locations.id', '=', 'organization_location.location_id')
            ->join('sports', 'sports.id', '=', 'organization_location_sport.sport_id')
            ->where('organizations.type', OrganizationType::Club)
            ->whereNotNull('locations.city')
            ->groupBy('locations.city', 'sports.id', 'sports.color')
            ->select(['locations.city', 'sports.color'])
            ->selectRaw('count(distinct locations.id) as location_count')
            ->orderByDesc('location_count')
            ->get()
            ->groupBy('city');
    }

    /**
     * The requested city, or null when it is missing or unknown — the page then
     * asks the visitor to pick one rather than choosing on their behalf.
     *
     * @param  list<array{name: string, ...}>  $cities
     */
    private function resolveCity(string $requested, array $cities): ?string
    {
        return in_array($requested, array_column($cities, 'name'), true)
            ? $requested
            : null;
    }

    /**
     * Locations matching the filters. Amenities narrow the list cumulatively:
     * picking parking AND showers leaves only places that have both, which is
     * what someone ticking a second box expects.
     *
     * @param  list<int>  $facilityIds
     * @return array<int, array<string, mixed>>
     */
    private function locations(?string $city, ?string $sport, array $facilityIds, ?string $search, ?LocationWay $way = null): array
    {
        $locations = Location::query()
            ->when($city, fn (Builder $query) => $query->where('city', $city))
            // The way in narrows the list to places that actually offer it. A
            // club programme lives on the presences; the other two on the spaces.
            ->when($way === LocationWay::Organised, fn (Builder $query) => $query->whereHas(
                'organizationLocations',
                fn (BuilderContract $presences) => $presences->whereHas(
                    'organization',
                    fn (BuilderContract $organizations) => $organizations->where('type', OrganizationType::Club),
                ),
            ))
            ->when($way?->accessMode() !== null, fn (Builder $query) => $query->whereHas(
                'spaces',
                fn (BuilderContract $spaces) => $spaces
                    ->where('status', FacilityStatus::Approved)
                    ->where('access_mode', $way?->accessMode()),
            ))
            ->when($search, fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($sport, fn (Builder $query) => $query->whereHas(
                'organizationLocations',
                fn (BuilderContract $presences) => $presences
                    // Spelled out rather than via OrganizationLocation::ofClubs():
                    // inside whereHas the builder is not typed to a model, so the
                    // scope would be invisible to static analysis.
                    ->whereHas('organization', fn (BuilderContract $organizations) => $organizations
                        ->where('type', OrganizationType::Club))
                    ->whereHas('sports', fn (BuilderContract $sports) => $sports->where('sports.slug', $sport)),
            ))
            ->when($facilityIds !== [], function (Builder $query) use ($facilityIds): void {
                foreach ($facilityIds as $facilityId) {
                    $query->whereHas(
                        'facilities',
                        fn (BuilderContract $facilities) => $facilities->whereKey($facilityId),
                    );
                }
            })
            ->withCount([
                'organizationLocations as club_count' => fn ($query) => $query
                    ->ofClubs()
                    ->when(
                        $sport,
                        fn (Builder $organizationLocations) => $organizationLocations->whereHas(
                            'sports',
                            fn (BuilderContract $sports) => $sports->where('sports.slug', $sport),
                        ),
                    ),
                'facilities as facility_count',
            ])
            // Only club presences carry the sport chips: a rentable court is a
            // different kind of offer and gets its own treatment in a later phase.
            ->with(['organizationLocations' => fn ($query) => $query->ofClubs()->with('sports')])
            ->orderBy('name')
            ->get();

        $liveIds = $this->liveLocationIds($locations->modelKeys());

        return $locations
            ->map(function (Location $location) use ($liveIds): array {
                $sports = $location->organizationLocations
                    ->flatMap(fn ($organizationLocation) => $organizationLocation->sports)
                    ->unique('id')
                    ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                    ->values();

                return [
                    'slug' => $location->slug,
                    'name' => $location->name,
                    'city' => $location->city ?? '',
                    'lat' => $location->latitude !== null ? (float) $location->latitude : null,
                    'lng' => $location->longitude !== null ? (float) $location->longitude : null,
                    'live' => $liveIds->contains($location->getKey()),
                    'clubCount' => (int) $location->club_count,
                    'facilityCount' => (int) $location->facility_count,
                    'color' => $sports->first()?->color,
                    'sports' => $sports
                        ->map(fn (Sport $sport): array => [
                            'key' => $sport->slug,
                            'label' => $sport->translated_name,
                        ])
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * Locations with a training session going on right now.
     *
     * @param  array<int, int|string>  $locationIds
     * @return Collection<int, int>
     */
    private function liveLocationIds(array $locationIds): Collection
    {
        if ($locationIds === []) {
            return collect();
        }

        $now = Carbon::now();

        return DB::table('schedule_slots')
            ->join('organization_location_sport', 'organization_location_sport.id', '=', 'schedule_slots.organization_location_sport_id')
            ->join('organization_location', 'organization_location.id', '=', 'organization_location_sport.organization_location_id')
            ->join('organizations', 'organizations.id', '=', 'organization_location.organization_id')
            ->where('organizations.type', OrganizationType::Club)
            ->whereIn('organization_location.location_id', $locationIds)
            ->where('schedule_slots.day_of_week', Weekday::fromDate($now)->value)
            ->where('schedule_slots.start_time', '<=', $now->format('H:i:s'))
            ->where('schedule_slots.end_time', '>=', $now->format('H:i:s'))
            ->distinct()
            ->pluck('organization_location.location_id');
    }

    /**
     * The sports taught in the city, with how much of each there is.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sports(?string $city): array
    {
        $stats = DB::table('organization_location_sport')
            ->join('organization_location', 'organization_location.id', '=', 'organization_location_sport.organization_location_id')
            ->join('organizations', 'organizations.id', '=', 'organization_location.organization_id')
            ->join('locations', 'locations.id', '=', 'organization_location.location_id')
            ->where('organizations.type', OrganizationType::Club)
            ->when($city, fn ($query) => $query->where('locations.city', $city))
            ->groupBy('organization_location_sport.sport_id')
            ->select([
                'organization_location_sport.sport_id',
                DB::raw('count(distinct locations.id) as location_count'),
                DB::raw('count(distinct organization_location.organization_id) as club_count'),
            ])
            ->get()
            ->keyBy('sport_id');

        if ($stats->isEmpty()) {
            return [];
        }

        $ages = $this->ageGroupsBySport($city);

        return Sport::query()
            ->whereIn('id', $stats->keys())
            ->get()
            ->sortByDesc(fn (Sport $sport): int => (int) $stats[$sport->getKey()]->location_count)
            ->map(fn (Sport $sport): array => [
                'key' => $sport->slug,
                'label' => $sport->translated_name,
                'icon' => (string) $sport->icon,
                'color' => $sport->color,
                'locationCount' => (int) $stats[$sport->getKey()]->location_count,
                'clubCount' => (int) $stats[$sport->getKey()]->club_count,
                'ages' => $ages->get($sport->getKey(), collect())->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Age group names offered for each sport in the city, keyed by sport id.
     *
     * @return Collection<int|string, Collection<int, mixed>>
     */
    private function ageGroupsBySport(?string $city): Collection
    {
        return DB::table('organization_sport_age_group')
            ->join('organization_sport', 'organization_sport.id', '=', 'organization_sport_age_group.organization_sport_id')
            ->join('age_groups', 'age_groups.id', '=', 'organization_sport_age_group.age_group_id')
            ->join('organization_location', 'organization_location.organization_id', '=', 'organization_sport.organization_id')
            ->join('organizations', 'organizations.id', '=', 'organization_location.organization_id')
            ->join('locations', 'locations.id', '=', 'organization_location.location_id')
            ->where('organizations.type', OrganizationType::Club)
            ->when($city, fn ($query) => $query->where('locations.city', $city))
            ->distinct()
            ->orderBy('age_groups.sort_order')
            ->select(['organization_sport.sport_id', 'age_groups.name', 'age_groups.sort_order'])
            ->get()
            ->groupBy('sport_id')
            ->map(fn (Collection $rows): Collection => $rows->pluck('name')->unique()->values());
    }

    /**
     * Amenities available somewhere in the city, used as list filters.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function facilities(?string $city): array
    {
        return Facility::query()
            // A club's unreviewed suggestion is not a filter anyone can trust.
            ->approved()
            ->whereHas('locations', fn (BuilderContract $query) => $query->when(
                $city,
                fn (BuilderContract $locations) => $locations->where('city', $city),
            ))
            // Curated order first (see FacilitySeeder); name only breaks ties,
            // so equal-weight amenities still come out in a stable order.
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Facility $facility): array => [
                'id' => $facility->getKey(),
                'name' => $facility->name,
                'icon' => $facility->icon,
            ])
            ->values()
            ->all();
    }
}
