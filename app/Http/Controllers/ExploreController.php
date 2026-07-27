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
     */
    public function index(Request $request): Response
    {
        $cities = $this->cities();
        $city = $this->resolveCity($request->string('oras')->trim()->toString(), $cities);
        $sport = $request->string('sport')->trim()->toString() ?: null;
        $facility = $request->integer('facilitate') ?: null;
        $search = $request->string('cauta')->trim()->toString() ?: null;

        return Inertia::render('public/explore/Index', [
            'city' => $city,
            'cities' => $cities->all(),
            'filters' => [
                'sport' => $sport,
                'facility' => $facility,
                'search' => $search,
            ],
            'locations' => $this->locations($city, $sport, $facility, $search),
            'sports' => $this->sports($city),
            'facilities' => $this->facilities($city),
        ]);
    }

    /**
     * Every city that has at least one location.
     *
     * @return Collection<int, string>
     */
    private function cities(): Collection
    {
        return Location::query()
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    /**
     * The requested city when it exists, otherwise the busiest one.
     *
     * @param  Collection<int, string>  $cities
     */
    private function resolveCity(string $requested, Collection $cities): ?string
    {
        if ($cities->contains($requested)) {
            return $requested;
        }

        return Location::query()
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByRaw('count(*) desc')
            ->value('city');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function locations(?string $city, ?string $sport, ?int $facility, ?string $search): array
    {
        $locations = Location::query()
            ->when($city, fn (Builder $query) => $query->where('city', $city))
            ->when($search, fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($sport, fn (Builder $query) => $query->whereHas(
                'clubLocations.sports',
                fn (BuilderContract $sports) => $sports->where('sports.slug', $sport),
            ))
            ->when($facility, fn (Builder $query) => $query->whereHas(
                'facilities',
                fn (BuilderContract $facilities) => $facilities->whereKey($facility),
            ))
            ->withCount([
                'clubLocations as club_count' => fn (BuilderContract $query) => $query->when(
                    $sport,
                    fn (BuilderContract $clubLocations) => $clubLocations->whereHas(
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
     * @return Collection<int, Collection<int, string>>
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
            ->whereHas('locations', fn (BuilderContract $query) => $query->when(
                $city,
                fn (BuilderContract $locations) => $locations->where('city', $city),
            ))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Facility $facility): array => [
                'id' => $facility->getKey(),
                'name' => $facility->name,
            ])
            ->all();
    }
}
