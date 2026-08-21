<?php

namespace App\Http\Controllers;

use App\Enums\FacilityStatus;
use App\Enums\LocationWay;
use App\Enums\OrganizationType;
use App\Enums\SpaceAccessMode;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Space;
use App\Models\Specialty;
use App\Models\Sport;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The three ways in, as browsable lists.
 *
 * Deliberately asymmetric. Two of them list organizations and one lists places,
 * because that is what the visitor is choosing: picking a swimming club for your
 * child means comparing coaches and groups, and you would follow a good one to
 * another pool. Wanting a swim after work means comparing distance, hours and
 * the price of a ticket — who runs the pool never enters into it.
 *
 * Nothing here reads what an organization *is*. A pool that also teaches appears
 * in both lists, out of two different offers.
 */
class DirectoryController extends Controller
{
    /**
     * Everybody who teaches, whatever else they do.
     */
    public function clubs(Request $request): Response
    {
        $counties = $this->counties();
        $county = $this->resolveCounty($request, $counties);
        $sport = $request->string('sport')->trim()->toString();

        $clubs = Organization::query()
            ->offering(OrganizationType::Club)
            ->when($county, fn (Builder $query) => $query->whereHas(
                'organizationLocations.location',
                fn (BuilderContract $locations) => $locations->where('county', $county),
            ))
            ->when($sport, fn (Builder $query) => $query->whereHas(
                'organizationSports.sport',
                fn (BuilderContract $sports) => $sports->where('slug', $sport),
            ))
            ->with(['organizationSports.sport', 'organizationLocations.location'])
            ->orderBy('name')
            ->get();

        return Inertia::render('public/directory/Clubs', [
            'clubs' => $this->presentClubs($clubs),
            'counties' => $counties,
            'sports' => $this->sportOptions(),
            'filters' => ['county' => $county, 'sport' => $sport === '' ? null : $sport],
        ]);
    }

    /**
     * Places you can get into on your own, whether or not anybody runs them.
     *
     * A location rather than a company: the park court has no operator at all,
     * and the visitor asking this question would not care if it did.
     */
    public function venues(Request $request): Response
    {
        $counties = $this->counties();
        $county = $this->resolveCounty($request, $counties);
        $sport = $request->string('sport')->trim()->toString();

        $locations = Location::query()
            ->whereHas('spaces', fn (BuilderContract $spaces) => $spaces
                ->where('status', FacilityStatus::Approved))
            ->when($county, fn (Builder $query) => $query->where('county', $county))
            ->when($sport, fn (Builder $query) => $query->whereHas(
                'spaces.sport',
                fn (BuilderContract $sports) => $sports->where('slug', $sport),
            ))
            ->with([
                'spaces' => fn ($query) => $query->approved(),
                'spaces.sport',
                'spaces.accessSlots',
                'spaces.organizationLocation.organization',
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('public/directory/Venues', [
            'venues' => $this->presentVenues($locations),
            'counties' => $counties,
            'sports' => $this->sportOptions(),
            'filters' => ['county' => $county, 'sport' => $sport === '' ? null : $sport],
        ]);
    }

    /**
     * Everybody who sells medical care to athletes.
     *
     * The same line the sport page draws: only medical specialties. A pilates
     * studio's massage is sold alongside a real activity rather than being one,
     * and nobody browsing this list is looking for it.
     */
    public function practices(Request $request): Response
    {
        $counties = $this->counties();
        $county = $this->resolveCounty($request, $counties);
        $specialty = $request->string('specialitate')->trim()->toString();

        $practices = Organization::query()
            ->whereHas('services', fn (BuilderContract $services) => $services
                ->whereHas('specialty', fn (BuilderContract $specialties) => $specialties
                    ->where('is_medical', true)))
            ->when($county, fn (Builder $query) => $query->whereHas(
                'organizationLocations.location',
                fn (BuilderContract $locations) => $locations->where('county', $county),
            ))
            ->when($specialty, fn (Builder $query) => $query->whereHas(
                'services.specialty',
                fn (BuilderContract $specialties) => $specialties->where('slug', $specialty),
            ))
            ->with(['services.specialty', 'organizationLocations.location'])
            ->orderBy('name')
            ->get();

        return Inertia::render('public/directory/Practices', [
            'practices' => $this->presentPractices($practices),
            'counties' => $counties,
            'specialties' => $this->specialtyOptions(),
            'filters' => ['county' => $county, 'specialty' => $specialty === '' ? null : $specialty],
        ]);
    }

    /**
     * @param  Collection<int, Organization>  $clubs
     * @return list<array<string, mixed>>
     */
    private function presentClubs(Collection $clubs): array
    {
        return array_values($clubs
            ->map(fn (Organization $club): array => [
                'slug' => $club->slug,
                'name' => $club->name,
                'sports' => $club->organizationSports
                    ->sortBy('sort_order')
                    ->map(fn ($organizationSport): array => [
                        'key' => $organizationSport->sport->slug,
                        'label' => $organizationSport->sport->translated_name,
                        'icon' => (string) $organizationSport->sport->icon,
                        'color' => $organizationSport->sport->color,
                    ])
                    ->values()
                    ->all(),
                'cities' => $club->organizationLocations
                    ->map(fn ($presence): ?string => $presence->location?->city)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->all());
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return list<array<string, mixed>>
     */
    private function presentVenues(Collection $locations): array
    {
        return array_values($locations
            ->map(function (Location $location): array {
                $ways = collect(SpaceAccessMode::cases())
                    ->filter(fn (SpaceAccessMode $mode): bool => $location->spaces
                        ->contains(fn (Space $space): bool => $space->accessModes()->contains($mode)))
                    ->map(fn (SpaceAccessMode $mode): array => [
                        'key' => LocationWay::forAccessMode($mode)->value,
                        'label' => $mode->label(),
                        'price' => $this->cheapest($location->spaces, $mode),
                    ])
                    ->values();

                return [
                    'slug' => $location->slug,
                    'name' => $location->name,
                    'city' => $location->city ?? '',
                    'address' => (string) $location->address,
                    'sports' => $location->spaces
                        ->map(fn (Space $space): ?array => $space->sport === null ? null : [
                            'key' => $space->sport->slug,
                            'label' => $space->sport->translated_name,
                            'icon' => (string) $space->sport->icon,
                        ])
                        ->filter()
                        ->unique('key')
                        ->values()
                        ->all(),
                    'ways' => $ways->all(),
                    // Nobody operating it is a fact worth showing, not a gap: a
                    // park court is free and open, and saying so is the point.
                    'unmanaged' => $location->spaces->every(fn (Space $space): bool => ! $space->isManaged()),
                ];
            })
            ->all());
    }

    /**
     * The cheapest price across these spaces for one way in, as a visitor reads
     * it. Null when nobody has published one — an unknown price is a real state.
     *
     * @param  Collection<int, Space>  $spaces
     */
    private function cheapest(Collection $spaces, SpaceAccessMode $mode): ?string
    {
        return $spaces
            ->filter(fn (Space $space): bool => $space->accessModes()->contains($mode))
            ->sortBy(fn (Space $space): float => $space->priceFrom($mode) ?? INF)
            ->map(fn (Space $space): ?string => $space->priceFromLabel($mode))
            ->filter()
            ->first();
    }

    /**
     * @param  Collection<int, Organization>  $practices
     * @return list<array<string, mixed>>
     */
    private function presentPractices(Collection $practices): array
    {
        return array_values($practices
            ->map(fn (Organization $practice): array => [
                'slug' => $practice->slug,
                'name' => $practice->name,
                'specialties' => $practice->services
                    ->map(fn ($service): ?Specialty => $service->specialty)
                    ->filter(fn (?Specialty $specialty): bool => $specialty?->is_medical === true)
                    ->unique('id')
                    ->sortBy('sort_order')
                    ->map(fn (Specialty $specialty): array => [
                        'key' => $specialty->slug,
                        'label' => $specialty->translated_name,
                        'icon' => (string) $specialty->icon,
                    ])
                    ->values()
                    ->all(),
                'cities' => $practice->organizationLocations
                    ->map(fn ($presence): ?string => $presence->location?->city)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string, icon: string}>
     */
    private function sportOptions(): array
    {
        return array_values(Sport::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Sport $sport): array => [
                'value' => $sport->slug,
                'label' => $sport->translated_name,
                'icon' => (string) $sport->icon,
            ])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string, icon: string}>
     */
    private function specialtyOptions(): array
    {
        return array_values(Specialty::query()
            ->where('is_medical', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Specialty $specialty): array => [
                'value' => $specialty->slug,
                'label' => $specialty->translated_name,
                'icon' => (string) $specialty->icon,
            ])
            ->all());
    }

    /**
     * @param  list<string>  $counties
     */
    private function resolveCounty(Request $request, array $counties): ?string
    {
        $county = $request->string('judet')->trim()->toString();

        return in_array($county, $counties, true) ? $county : null;
    }

    /**
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
