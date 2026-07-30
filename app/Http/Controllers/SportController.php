<?php

namespace App\Http\Controllers;

use App\Enums\FacilityStatus;
use App\Enums\LocationWay;
use App\Enums\OrganizationType;
use App\Enums\ScheduleSlotKind;
use App\Models\Level;
use App\Models\Location;
use App\Models\Space;
use App\Models\Sport;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SportController extends Controller
{
    /**
     * Every sport taught anywhere, as a way in for a visitor who knows what
     * they want to play but not where. Picking one leads to the explore page,
     * which then asks only for the city.
     *
     * Narrowing by county comes first in the form, because "what can I play
     * near me" is the question people actually arrive with — the counts then
     * describe that county rather than the whole country.
     */
    public function index(Request $request): Response
    {
        $counties = $this->counties();
        $county = $request->string('judet')->trim()->toString();
        $county = in_array($county, $counties, true) ? $county : null;

        return Inertia::render('public/sports/Index', [
            'sports' => Sport::withReach($county),
            'counties' => $counties,
            'filters' => ['county' => $county],
        ]);
    }

    /**
     * One sport, and where you can actually do it — the page someone searching
     * "baschet cluj" should land on.
     *
     * Without a city it is a picker over the cities that have this sport. With
     * one, it answers the real question: where, and how do I get in. Grouped by
     * the way in rather than by who owns the place, the same axis the location
     * page uses.
     */
    public function show(Request $request, string $slug, ?string $city = null): Response
    {
        $sport = Sport::query()->where('slug', $slug)->firstOrFail();

        $cities = $this->citiesFor($sport);
        $city = $this->resolveCity($city, $cities);

        // "Înot de inițiere pentru copii" is the commonest real search on a page
        // like this. Unknown values are ignored rather than emptying the list.
        $levels = $city === null ? [] : $this->levelsInCity($sport, $city);
        $level = collect($levels)->firstWhere('slug', Str::slug($request->string('nivel')->trim()->toString()));

        return Inertia::render('public/sports/Show', [
            'sport' => [
                'key' => $sport->slug,
                'label' => $sport->translated_name,
                'icon' => (string) $sport->icon,
                'color' => $sport->color,
            ],
            'city' => $city,
            'cities' => $cities,
            'levels' => $levels,
            'filters' => ['level' => $level['slug'] ?? null],
            'ways' => $city === null ? [] : $this->waysInCity($sport, $city, $level['id'] ?? null),
        ]);
    }

    /**
     * The levels actually taught for this sport in this city, in order. Offering
     * a level nobody teaches here would be offering a dead end.
     *
     * @return list<array{id: int, name: string, slug: string}>
     */
    private function levelsInCity(Sport $sport, string $city): array
    {
        return array_values(Level::query()
            ->whereHas('organizationSports', fn (BuilderContract $sports) => $sports
                ->where('sport_id', $sport->getKey())
                ->whereHas('organization', fn (BuilderContract $organizations) => $organizations
                    ->where('type', OrganizationType::Club)
                    ->whereHas('organizationLocations.location', fn (BuilderContract $locations) => $locations
                        ->where('city', $city))))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Level $level): array => [
                'id' => $level->getKey(),
                'name' => $level->name,
                'slug' => Str::slug($level->name),
            ])
            ->all());
    }

    /**
     * Cities where this sport can be done at all, busiest first, each with how
     * many ways in there are.
     *
     * @return list<array{name: string, slug: string, locationCount: int, ways: array<string, int>}>
     */
    private function citiesFor(Sport $sport): array
    {
        $counts = collect(LocationWay::cases())
            ->mapWithKeys(fn (LocationWay $way): array => [
                $way->value => $this->locationsQuery($sport, $way)
                    ->whereNotNull('locations.city')
                    ->reorder()
                    ->groupBy('locations.city')
                    ->selectRaw('locations.city, count(distinct locations.id) as total')
                    ->pluck('total', 'city'),
            ]);

        return array_values($counts
            ->flatMap(fn (Collection $byCity): array => $byCity->keys()->all())
            ->unique()
            ->sort()
            ->map(function (string $city) use ($counts): array {
                $ways = $counts
                    ->map(fn (Collection $byCity): int => (int) $byCity->get($city, 0))
                    ->filter(fn (int $count): bool => $count > 0)
                    ->all();

                return [
                    'name' => $city,
                    'slug' => Str::slug($city),
                    'locationCount' => max($ways ?: [0]),
                    'ways' => $ways,
                ];
            })
            ->sortByDesc('locationCount')
            ->values()
            ->all());
    }

    /**
     * The requested city, matched on its slug so the URL can stay readable, or
     * null when it is missing or has none of this sport.
     *
     * @param  list<array{name: string, slug: string, locationCount: int, ways: array<string, int>}>  $cities
     */
    private function resolveCity(?string $requested, array $cities): ?string
    {
        if ($requested === null) {
            return null;
        }

        $slug = Str::slug($requested);

        return collect($cities)->firstWhere('slug', $slug)['name'] ?? null;
    }

    /**
     * The ways into this sport in one city, each with the places that offer it.
     * Ways nobody offers here are absent rather than shown empty.
     *
     * @return list<array{key: string, label: string, description: string, locations: list<array<string, mixed>>}>
     */
    private function waysInCity(Sport $sport, string $city, ?int $levelId = null): array
    {
        return array_values(collect(LocationWay::cases())
            // A level is a property of an organised programme. Filtering by one
            // and still listing rentable courts would answer a question nobody
            // asked, so the other two ways drop out entirely.
            ->filter(fn (LocationWay $way): bool => $levelId === null || $way === LocationWay::Organised)
            ->map(function (LocationWay $way) use ($sport, $city, $levelId): array {
                $locations = $this->locationsQuery($sport, $way, $levelId)
                    ->where('locations.city', $city)
                    ->select('locations.*')
                    ->distinct()
                    ->orderBy('locations.name')
                    ->get();

                return [
                    'key' => $way->value,
                    'label' => $way->label(),
                    'description' => $way->description(),
                    'locations' => array_values($locations
                        ->map(fn (Location $location): array => $this->locationCard($location, $sport, $way))
                        ->all()),
                ];
            })
            ->filter(fn (array $way): bool => $way['locations'] !== [])
            ->all());
    }

    /**
     * Locations offering this sport through one way in.
     *
     * @return Builder<Location>
     */
    private function locationsQuery(Sport $sport, LocationWay $way, ?int $levelId = null): Builder
    {
        $mode = $way->accessMode();

        if ($mode === null) {
            return Location::query()->whereHas(
                'organizationLocations',
                fn (BuilderContract $presences) => $presences
                    ->whereHas('organization', fn (BuilderContract $organizations) => $organizations
                        ->where('type', OrganizationType::Club)
                        ->when($levelId, fn (BuilderContract $clubs) => $clubs->whereHas(
                            'organizationSports',
                            fn (BuilderContract $sports) => $sports
                                ->where('sport_id', $sport->getKey())
                                ->whereHas('levels', fn (BuilderContract $levels) => $levels->whereKey($levelId)),
                        )))
                    ->whereHas('sports', fn (BuilderContract $sports) => $sports->whereKey($sport->getKey())),
            );
        }

        // The way in is spelled out rather than calling Space::scopeOffering():
        // inside whereHas the builder is not typed to a model, so the scope would
        // be invisible to static analysis. Same shape as the scope — the space's
        // own mode, or any interval that overrides it.
        return Location::query()->whereHas(
            'spaces',
            fn (BuilderContract $spaces) => $spaces
                ->where('status', FacilityStatus::Approved)
                ->where('sport_id', $sport->getKey())
                ->where(fn (BuilderContract $offering) => $offering
                    ->where('access_mode', $mode)
                    ->orWhereHas('scheduleSlots', fn (BuilderContract $slots) => $slots
                        ->where('kind', ScheduleSlotKind::Access)
                        ->where('access_mode', $mode))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function locationCard(Location $location, Sport $sport, LocationWay $way): array
    {
        $mode = $way->accessMode();

        // Filtered in PHP rather than re-queried: `spaces` is already eager
        // loaded for the card, and `offering()` lives on the Space builder which
        // a HasMany relation does not expose to static analysis.
        $spaces = $mode === null
            ? new Collection
            : Space::query()
                ->approved()
                ->where('location_id', $location->getKey())
                ->where('sport_id', $sport->getKey())
                ->offering($mode)
                ->with(['accessSlots', 'organizationLocation.organization'])
                ->get()
                ->pipe(fn (EloquentCollection $spaces): Collection => collect($spaces->all()));

        return [
            'slug' => $location->slug,
            'name' => $location->name,
            'address' => collect([$location->address, $location->city])->filter()->implode(', '),
            'price' => $mode === null
                ? null
                : $spaces->map(fn (Space $space): ?string => $space->priceFromLabel($mode))->filter()->first(),
            'openNow' => $mode === null
                ? null
                : $spaces->contains(fn (Space $space): bool => $space->openAt(null, $mode)),
            'who' => $mode === null
                ? trans_choice('{1} un club|[2,*] :count cluburi', $this->clubCount($location, $sport))
                : $this->operators($spaces),
        ];
    }

    private function clubCount(Location $location, Sport $sport): int
    {
        return $location->organizationLocations()
            ->ofClubs()
            ->whereHas('sports', fn (BuilderContract $sports) => $sports->whereKey($sport->getKey()))
            ->count();
    }

    /**
     * @param  Collection<int, Space>  $spaces
     */
    private function operators(Collection $spaces): string
    {
        $names = $spaces
            ->map(fn (Space $space): ?string => $space->organizationLocation?->organization->name)
            ->filter()
            ->unique();

        return $names->isEmpty() ? 'Spațiu public' : $names->implode(' · ');
    }

    /**
     * Counties that actually hold a location. Offering the other thirty-odd
     * would be offering dead ends.
     *
     * @return list<string>
     */
    private function counties(): array
    {
        return array_values(Location::query()
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->all());
    }
}
