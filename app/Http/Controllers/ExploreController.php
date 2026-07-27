<?php

namespace App\Http\Controllers;

use App\Enums\Weekday;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Sport;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
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
        $cities = $this->cities();
        $city = $this->resolveCity($request->string('oras')->trim()->toString(), $cities);
        $sport = $request->string('sport')->trim()->toString() ?: null;
        $facilities = array_values($request->collect('facilitati')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->all());
        $search = $request->string('cauta')->trim()->toString() ?: null;

        return Inertia::render('public/explore/Index', [
            'city' => $city,
            'cities' => $cities,
            'filters' => [
                'sport' => $sport,
                'facilities' => $facilities,
                'search' => $search,
            ],
            'locations' => $city === null ? [] : $this->locations($city, $sport, $facilities, $search),
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
     * @return list<array{name: string, locationCount: int, clubCount: int, sportCount: int, color: string|null, lat: float|null, lng: float|null}>
     */
    private function cities(): array
    {
        $topSports = $this->topSportsByCity();

        return array_values(DB::table('locations')
            ->leftJoin('club_location', 'club_location.location_id', '=', 'locations.id')
            ->whereNotNull('locations.city')
            ->groupBy('locations.city')
            ->select(['locations.city'])
            ->selectRaw('count(distinct locations.id) as location_count')
            ->selectRaw('count(distinct club_location.club_id) as club_count')
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
        return DB::table('club_location_sport')
            ->join('club_location', 'club_location.id', '=', 'club_location_sport.club_location_id')
            ->join('locations', 'locations.id', '=', 'club_location.location_id')
            ->join('sports', 'sports.id', '=', 'club_location_sport.sport_id')
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
    private function locations(?string $city, ?string $sport, array $facilityIds, ?string $search): array
    {
        $locations = Location::query()
            ->when($city, fn (Builder $query) => $query->where('city', $city))
            ->when($search, fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($sport, fn (Builder $query) => $query->whereHas(
                'clubLocations.sports',
                fn (BuilderContract $sports) => $sports->where('sports.slug', $sport),
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
                'clubLocations as club_count' => fn (Builder $query) => $query->when(
                    $sport,
                    fn (Builder $clubLocations) => $clubLocations->whereHas(
                        'sports',
                        fn (BuilderContract $sports) => $sports->where('sports.slug', $sport),
                    ),
                ),
                'facilities as facility_count',
            ])
            ->with(['clubLocations.sports'])
            ->orderBy('name')
            ->get();

        $liveIds = $this->liveLocationIds($locations->modelKeys());

        return $locations
            ->map(function (Location $location) use ($liveIds): array {
                $sports = $location->clubLocations
                    ->flatMap(fn ($clubLocation) => $clubLocation->sports)
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
            ->join('club_location_sport', 'club_location_sport.id', '=', 'schedule_slots.club_location_sport_id')
            ->join('club_location', 'club_location.id', '=', 'club_location_sport.club_location_id')
            ->whereIn('club_location.location_id', $locationIds)
            ->where('schedule_slots.day_of_week', Weekday::fromDate($now)->value)
            ->where('schedule_slots.start_time', '<=', $now->format('H:i:s'))
            ->where('schedule_slots.end_time', '>=', $now->format('H:i:s'))
            ->distinct()
            ->pluck('club_location.location_id');
    }

    /**
     * The sports taught in the city, with how much of each there is.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sports(?string $city): array
    {
        $stats = DB::table('club_location_sport')
            ->join('club_location', 'club_location.id', '=', 'club_location_sport.club_location_id')
            ->join('locations', 'locations.id', '=', 'club_location.location_id')
            ->when($city, fn ($query) => $query->where('locations.city', $city))
            ->groupBy('club_location_sport.sport_id')
            ->select([
                'club_location_sport.sport_id',
                DB::raw('count(distinct locations.id) as location_count'),
                DB::raw('count(distinct club_location.club_id) as club_count'),
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
        return DB::table('club_sport_age_group')
            ->join('club_sport', 'club_sport.id', '=', 'club_sport_age_group.club_sport_id')
            ->join('age_groups', 'age_groups.id', '=', 'club_sport_age_group.age_group_id')
            ->join('club_location', 'club_location.club_id', '=', 'club_sport.club_id')
            ->join('locations', 'locations.id', '=', 'club_location.location_id')
            ->when($city, fn ($query) => $query->where('locations.city', $city))
            ->distinct()
            ->orderBy('age_groups.sort_order')
            ->select(['club_sport.sport_id', 'age_groups.name', 'age_groups.sort_order'])
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
