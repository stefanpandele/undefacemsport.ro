<?php

namespace App\Http\Controllers;

use App\Concerns\PresentsOrganizations;
use App\Enums\ContactType;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\OrganizationLocationSport;
use App\Models\OrganizationSport;
use App\Models\Person;
use App\Models\Space;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    use PresentsOrganizations;

    /**
     * Show a single sports location: its amenities, the sports played here and
     * every club teaching them, each with its own weekly schedule.
     */
    public function show(Request $request, string $slug): Response
    {
        $location = Location::query()
            ->where('slug', $slug)
            ->with([
                // Amenities a club proposed but no admin has reviewed yet are
                // not shown to visitors.
                'facilities' => fn ($query) => $query->approved(),
                // Club presences only. The page's club blocks, occupancy badges
                // and "hall is taken" cards are all about organized programmes;
                // rentable spaces are a different offer, added in a later phase.
                'organizationLocations' => fn ($query) => $query->ofClubs(),
                'organizationLocations.organization.contacts',
                'organizationLocations.organization.people.sports',
                'organizationLocations.organization.organizationSports.sport',
                'organizationLocations.organization.organizationSports.benefits',
                'organizationLocations.organization.organizationSports.ageGroups',
                'organizationLocations.organization.organizationSports.galleryImages',
                'organizationLocations.organizationLocationSports.sport',
                'organizationLocations.organizationLocationSports.scheduleSlots.ageGroup',
                // The other two ways in. Only what an admin has cleared, the same
                // rule the amenities follow.
                'spaces' => fn ($query) => $query->approved(),
                'spaces.sport',
                'spaces.accessSlots',
                'spaces.organizationLocation.organization',
            ])
            ->firstOrFail();

        $clubs = $this->clubBlocks($location);
        $sports = $this->sports($location, $clubs);
        $requested = $request->string('sport')->toString();

        return Inertia::render('public/locations/Show', [
            'location' => $this->presentLocation($location, $clubs, $sports),
            // Arriving from the explore page with a sport filter opens the page
            // on that sport instead of the "pick a sport" prompt.
            'activeSport' => in_array($requested, array_column($sports, 'key'), true)
                ? $requested
                : null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $clubs
     * @param  list<array{key: string, label: string, icon: string, color: string|null, clubCount: int}>  $sports
     * @return array<string, mixed>
     */
    private function presentLocation(Location $location, array $clubs, array $sports): array
    {
        return [
            'slug' => $location->slug,
            'name' => $location->name,
            'address' => collect([$location->address, $location->city])->filter()->implode(', '),
            'city' => $location->city ?? '',
            'lat' => $location->latitude !== null ? (float) $location->latitude : null,
            'lng' => $location->longitude !== null ? (float) $location->longitude : null,
            'facilities' => $location->facilities
                ->sortBy('sort_order')
                ->map(fn (Facility $facility): array => [
                    'icon' => $facility->icon ?? '🛠',
                    'label' => $facility->name,
                ])
                ->values()
                ->all(),
            'sports' => $sports,
            'clubs' => $clubs,
            // Keyed by sport, then the ways in that actually exist there. The page
            // offers a choice only where there is one to make.
            'ways' => $this->waysIn($location, $sports, $clubs),
            // Belongs to the place, not to any one offer — which is why it sits
            // outside the sport sections rather than inside a club's card.
            'day' => $this->day($location),
        ];
    }

    /**
     * Every way into this location, grouped by sport.
     *
     * The axis is how you get in, not who owns what: a club's programme, walking
     * in off the street, or booking the whole space. A sport shows only the ways
     * that exist at this address, so a pool with no venue signed up still just
     * lists its clubs.
     *
     * @param  list<array{key: string, label: string, icon: string, color: string|null, clubCount: int}>  $sports
     * @param  list<array<string, mixed>>  $clubs
     * @return array<string, list<array<string, mixed>>>
     */
    private function waysIn(Location $location, array $sports, array $clubs): array
    {
        // A space without a sport — a sauna, a changing block — groups under the
        // empty key and is dropped: it is a thing at the place, not a way to play
        // a sport, so it has no column on this page.
        $spacesBySport = $location->spaces->groupBy(function (Space $space): string {
            $sport = $space->sport;

            return $sport === null ? '' : $sport->slug;
        });
        $clubsBySport = collect($clubs)->groupBy('sport');

        $keys = collect($sports)->pluck('key')
            ->merge($spacesBySport->keys()->filter())
            ->unique()
            ->values();

        return $keys
            ->mapWithKeys(function (string $sportKey) use ($clubsBySport, $spacesBySport): array {
                $ways = [];

                $sportClubs = $clubsBySport->get($sportKey, collect());

                if ($sportClubs->isNotEmpty()) {
                    $ways[] = [
                        'key' => 'organizat',
                        'verb' => 'Mă înscriu la un club',
                        'how' => 'Antrenamente recurente, pe grupe, cu antrenor',
                        // Clubs do not publish prices, and inventing one would be
                        // worse than saying nothing.
                        'price' => null,
                        'who' => trans_choice('{1} un club|[2,*] :count cluburi', $sportClubs->count()),
                        'spaces' => [],
                    ];
                }

                foreach ([SpaceAccessMode::OpenAccess, SpaceAccessMode::ExclusiveRental] as $mode) {
                    $spaces = $spacesBySport->get($sportKey, collect())
                        ->filter(fn (Space $space): bool => $space->accessModes()->contains($mode));

                    if ($spaces->isEmpty()) {
                        continue;
                    }

                    $ways[] = [
                        'key' => $mode === SpaceAccessMode::OpenAccess ? 'liber' : 'inchiriere',
                        'verb' => $mode->verb(),
                        'how' => $mode->description(),
                        'price' => $this->cheapest($spaces, $mode),
                        'who' => $this->operators($spaces),
                        'spaces' => $spaces
                            ->map(fn (Space $space): array => $this->presentSpace($space, $mode))
                            ->values()
                            ->all(),
                    ];
                }

                return [$sportKey => $ways];
            })
            ->filter(fn (array $ways): bool => $ways !== [])
            ->all();
    }

    /**
     * The lowest price across these spaces for one way in, as a visitor reads it.
     *
     * @param  Collection<int, Space>  $spaces
     */
    private function cheapest(Collection $spaces, SpaceAccessMode $mode): ?string
    {
        $labels = $spaces
            ->map(fn (Space $space): ?string => $space->priceFromLabel($mode))
            ->filter();

        if ($labels->isEmpty()) {
            return null;
        }

        $cheapest = $spaces
            ->map(fn (Space $space): ?float => $space->priceFrom($mode))
            ->filter(fn (?float $price): bool => $price !== null)
            ->sort()
            ->keys()
            ->first();

        return $cheapest === null ? $labels->first() : $spaces[$cheapest]->priceFromLabel($mode);
    }

    /**
     * Who runs these spaces, or that nobody does.
     *
     * @param  Collection<int, Space>  $spaces
     */
    private function operators(Collection $spaces): string
    {
        $names = $spaces
            ->map(fn (Space $space): ?string => $space->organizationLocation?->organization->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? 'Spațiu public, neadministrat' : $names->implode(' · ');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSpace(Space $space, SpaceAccessMode $mode): array
    {
        $today = Weekday::fromDate(Carbon::now())->value;

        return [
            'id' => $space->getKey(),
            'name' => $space->name,
            'operator' => $space->organizationLocation?->organization->name,
            'unmanaged' => ! $space->isManaged(),
            'price' => $space->priceFromLabel($mode),
            'priceNotes' => $space->price_notes,
            'isFree' => $space->isFree(),
            'capacity' => $space->capacity,
            'isIndoor' => $space->is_indoor,
            'hasFloodlights' => $space->has_floodlights,
            'surface' => $space->surface,
            'openNow' => $space->openAt(null, $mode),
            'closesAt' => $space->closesAt(null, $mode),
            'lastVerified' => $space->last_verified_at?->diffForHumans(),
            'today' => $space->hoursByDay($mode)[$today] ?? [],
            'week' => $this->weekHours($space, $mode),
        ];
    }

    /**
     * Opening hours as a week, one row per day, closed days included so a visitor
     * can see the shape of the week rather than infer it from gaps.
     *
     * @return list<array{day: string, hours: string}>
     */
    private function weekHours(Space $space, SpaceAccessMode $mode): array
    {
        $byDay = $space->hoursByDay($mode);

        return array_values(collect(Weekday::cases())
            ->map(function (Weekday $day) use ($byDay): array {
                $intervals = collect($byDay[$day->value] ?? [])
                    ->map(fn (array $interval): string => $interval['start'].'–'.$interval['end'])
                    ->implode(', ');

                return [
                    'day' => $day->label(),
                    'hours' => $intervals === '' ? 'închis' : $intervals,
                ];
            })
            ->all());
    }

    /**
     * Today at this location: one row per space, with what is happening in it.
     *
     * A view of the place, not of anybody's offer. Both worlds may appear here
     * precisely because the subject is the location — inside an offer's own card
     * they must not, or a club would be credited with somebody else's programme.
     *
     * @return array<string, mixed>|null
     */
    private function day(Location $location): ?array
    {
        if ($location->spaces->isEmpty()) {
            return null;
        }

        $today = Weekday::fromDate(Carbon::now());

        $rows = $location->spaces
            ->map(function (Space $space) use ($today): ?array {
                $bars = collect($space->hoursByDay()[$today->value] ?? [])
                    ->map(fn (array $interval): array => [
                        'start' => (int) substr($interval['start'], 0, 2),
                        'end' => (int) substr($interval['end'], 0, 2),
                        'label' => $space->accessModes()->count() > 1
                            ? $space->name
                            : ($space->isFree() ? 'Acces liber, gratuit' : (string) $space->priceFromLabel()),
                    ])
                    ->values();

                if ($bars->isEmpty()) {
                    return null;
                }

                return [
                    'name' => $space->name,
                    'sub' => $space->capacity === null ? '' : $space->capacity.' locuri',
                    'bars' => $bars->all(),
                ];
            })
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'label' => 'Azi, '.mb_strtolower($today->label()),
            'from' => 7,
            'to' => 23,
            'rows' => $rows->all(),
        ];
    }

    /**
     * The sports taught here, with how many clubs teach each of them.
     *
     * @param  list<array<string, mixed>>  $clubs
     * @return list<array{key: string, label: string, icon: string, color: string|null, clubCount: int}>
     */
    private function sports(Location $location, array $clubs): array
    {
        $clubCounts = collect($clubs)->countBy('sport');

        return array_values($location->organizationLocations
            ->flatMap(fn (OrganizationLocation $organizationLocation) => $organizationLocation->organizationLocationSports)
            ->map(fn (OrganizationLocationSport $organizationLocationSport): Sport => $organizationLocationSport->sport)
            ->unique('id')
            ->sortBy(fn (Sport $sport): string => $sport->translated_name)
            ->map(fn (Sport $sport): array => [
                'key' => $sport->slug,
                'label' => $sport->translated_name,
                'icon' => (string) $sport->icon,
                'color' => $sport->color,
                'clubCount' => (int) $clubCounts->get($sport->slug, 0),
            ])
            ->all());
    }

    /**
     * One block per (club, sport) taught here — the same club appears once for
     * every sport it teaches at this location, with the schedule for it.
     *
     * @return list<array<string, mixed>>
     */
    private function clubBlocks(Location $location): array
    {
        $occupancy = $this->occupancy($location);

        return array_values($location->organizationLocations
            ->flatMap(fn (OrganizationLocation $organizationLocation) => $organizationLocation->organizationLocationSports
                ->map(fn (OrganizationLocationSport $organizationLocationSport): array => $this->clubBlock(
                    $organizationLocation->organization,
                    $organizationLocationSport,
                    $occupancy,
                )))
            ->sortBy('name')
            ->all());
    }

    /**
     * Who trains in this hall, indexed by sport, weekday and interval. Built
     * once for the whole location and then narrowed per club block, so a page
     * with many clubs still walks the slots a single time.
     *
     * Each entry carries the club's block key as well as its name, so the page
     * can link straight to that club's block further down.
     *
     * @return array<int, array<int, array<string, array<int, array{name: string, key: string}>>>> sport => day => interval => club id => club
     */
    private function occupancy(Location $location): array
    {
        $map = [];

        foreach ($location->organizationLocations as $organizationLocation) {
            $organization = $organizationLocation->organization;

            foreach ($organizationLocation->organizationLocationSports as $organizationLocationSport) {
                $entry = [
                    'name' => $organization->name,
                    'key' => $this->blockKey($organization, $organizationLocationSport->sport),
                ];

                foreach ($organizationLocationSport->scheduleSlots as $slot) {
                    $map[$organizationLocationSport->sport_id][$slot->day_of_week->value][$this->interval($slot)][$organization->getKey()] = $entry;
                }
            }
        }

        return $map;
    }

    /**
     * Identifies one (club, sport) block on the page — also the anchor the
     * hall-occupancy modal links to.
     */
    private function blockKey(Organization $organization, Sport $sport): string
    {
        return $organization->slug.'-'.$sport->slug;
    }

    /**
     * The occupancy of this hall for one club's sport, with that club itself
     * removed — what is left is "who else is here".
     *
     * Shuffled on every request: the map is built in club id order, so keeping
     * it would put whoever registered first permanently at the top of every
     * list. No club should get a standing advantage from being early.
     *
     * @param  array<int, array<int, array<string, array<int, array{name: string, key: string}>>>>  $occupancy
     * @return array<int, array<string, list<array{name: string, key: string}>>>
     */
    private function otherClubs(array $occupancy, Organization $organization, int $sportId): array
    {
        return collect($occupancy[$sportId] ?? [])
            ->map(fn (array $byInterval): array => collect($byInterval)
                ->map(fn (array $clubs): array => array_values(collect($clubs)->except($organization->getKey())->shuffle()->all()))
                ->reject(fn (array $clubs): bool => $clubs === [])
                ->all())
            ->reject(fn (array $byInterval): bool => $byInterval === [])
            ->all();
    }

    /**
     * @param  array<int, array<int, array<string, array<int, array{name: string, key: string}>>>>  $occupancy
     * @return array<string, mixed>
     */
    private function clubBlock(Organization $organization, OrganizationLocationSport $organizationLocationSport, array $occupancy = []): array
    {
        $sport = $organizationLocationSport->sport;
        $organizationSport = $organization->organizationSports->firstWhere('sport_id', $sport->getKey());
        $people = $this->coachesForSport($organization, $sport);
        $primary = $people->first();

        return [
            'key' => $this->blockKey($organization, $sport),
            'slug' => $organization->slug,
            'sport' => $sport->slug,
            'name' => $organization->name,
            'representative' => $this->representative($primary),
            'about' => $organization->description ?? '',
            'photos' => $organizationSport instanceof OrganizationSport
                ? $organizationSport->galleryImages->map(fn ($image): string => $image->url)->values()->all()
                : [],
            'trustChips' => $this->presentTrustChips($organizationSport),
            'ages' => $organizationSport instanceof OrganizationSport
                ? $organizationSport->ageGroups->sortBy('sort_order')->pluck('name')->values()->all()
                : [],
            'people' => $this->presentPeople($people),
            'schedule' => $this->presentWeek(
                $organizationLocationSport->scheduleSlots,
                $this->otherClubs($occupancy, $organization, $sport->getKey()),
            ),
            'contactName' => $primary->name ?? $organization->name,
            'contactPhone' => $organization->contacts->firstWhere('type', ContactType::Phone)?->value,
        ];
    }

    /**
     * A club's people for one sport, falling back to all of them when none is
     * assigned to it.
     *
     * @return Collection<int, Person>
     */
    private function coachesForSport(Organization $organization, Sport $sport): Collection
    {
        $forSport = $organization->people->filter(
            fn (Person $person): bool => $person->sports->contains('id', $sport->getKey()),
        );

        // Same order the block presents them in, so the first one is also the
        // club's representative and contact.
        return ($forSport->isNotEmpty() ? $forSport : $organization->people)
            ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])
            ->values();
    }

    private function representative(?Person $person): string
    {
        if (! $person instanceof Person) {
            return '';
        }

        return $person->role ? "{$person->name}, {$person->role}" : $person->name;
    }
}
