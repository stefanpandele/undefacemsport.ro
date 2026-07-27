<?php

namespace App\Http\Controllers;

use App\Concerns\PresentsClubs;
use App\Enums\ContactType;
use App\Models\Club;
use App\Models\ClubLocation;
use App\Models\ClubLocationSport;
use App\Models\ClubSport;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    use PresentsClubs;

    /**
     * Show a single sports location: its amenities, the sports played here and
     * every club teaching them, each with its own weekly schedule.
     */
    public function show(Request $request, string $slug): Response
    {
        $location = Location::query()
            ->where('slug', $slug)
            ->with([
                'facilities',
                'clubLocations.club.contacts',
                'clubLocations.club.coaches.sports',
                'clubLocations.club.clubSports.sport',
                'clubLocations.club.clubSports.benefits',
                'clubLocations.club.clubSports.ageGroups',
                'clubLocations.club.clubSports.galleryImages',
                'clubLocations.clubLocationSports.sport',
                'clubLocations.clubLocationSports.scheduleSlots.ageGroup',
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

        return array_values($location->clubLocations
            ->flatMap(fn (ClubLocation $clubLocation) => $clubLocation->clubLocationSports)
            ->map(fn (ClubLocationSport $clubLocationSport): Sport => $clubLocationSport->sport)
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

        return array_values($location->clubLocations
            ->flatMap(fn (ClubLocation $clubLocation) => $clubLocation->clubLocationSports
                ->map(fn (ClubLocationSport $clubLocationSport): array => $this->clubBlock(
                    $clubLocation->club,
                    $clubLocationSport,
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
     * @return array<int, array<int, array<string, array<int, string>>>> sport => day => interval => club id => club name
     */
    private function occupancy(Location $location): array
    {
        $map = [];

        foreach ($location->clubLocations as $clubLocation) {
            $club = $clubLocation->club;

            foreach ($clubLocation->clubLocationSports as $clubLocationSport) {
                foreach ($clubLocationSport->scheduleSlots as $slot) {
                    $map[$clubLocationSport->sport_id][$slot->day_of_week->value][$this->interval($slot)][$club->getKey()] = $club->name;
                }
            }
        }

        return $map;
    }

    /**
     * The occupancy of this hall for one club's sport, with that club itself
     * removed — what is left is "who else is here".
     *
     * @param  array<int, array<int, array<string, array<int, string>>>>  $occupancy
     * @return array<int, array<string, list<string>>>
     */
    private function otherClubs(array $occupancy, Club $club, int $sportId): array
    {
        return collect($occupancy[$sportId] ?? [])
            ->map(fn (array $byInterval): array => collect($byInterval)
                ->map(fn (array $clubs): array => array_values(collect($clubs)->except($club->getKey())->all()))
                ->reject(fn (array $clubs): bool => $clubs === [])
                ->all())
            ->reject(fn (array $byInterval): bool => $byInterval === [])
            ->all();
    }

    /**
     * @param  array<int, array<int, array<string, array<int, string>>>>  $occupancy
     * @return array<string, mixed>
     */
    private function clubBlock(Club $club, ClubLocationSport $clubLocationSport, array $occupancy = []): array
    {
        $sport = $clubLocationSport->sport;
        $clubSport = $club->clubSports->firstWhere('sport_id', $sport->getKey());
        $coaches = $this->coachesForSport($club, $sport);
        $primary = $coaches->first();

        return [
            'key' => $club->slug.'-'.$sport->slug,
            'slug' => $club->slug,
            'sport' => $sport->slug,
            'name' => $club->name,
            'representative' => $this->representative($primary),
            'about' => $club->description ?? '',
            'photos' => $clubSport instanceof ClubSport
                ? $clubSport->galleryImages->map(fn ($image): string => $image->url)->values()->all()
                : [],
            'trustChips' => $this->presentTrustChips($clubSport),
            'ages' => $clubSport instanceof ClubSport
                ? $clubSport->ageGroups->sortBy('sort_order')->pluck('name')->values()->all()
                : [],
            'coaches' => $this->presentCoaches($coaches),
            'schedule' => $this->presentWeek(
                $clubLocationSport->scheduleSlots,
                $this->otherClubs($occupancy, $club, $sport->getKey()),
            ),
            'contactName' => $primary->name ?? $club->name,
            'contactPhone' => $club->contacts->firstWhere('type', ContactType::Phone)?->value,
        ];
    }

    /**
     * A club's coaches for one sport, falling back to all of them when none is
     * assigned to it.
     *
     * @return Collection<int, Coach>
     */
    private function coachesForSport(Club $club, Sport $sport): Collection
    {
        $forSport = $club->coaches->filter(
            fn (Coach $coach): bool => $coach->sports->contains('id', $sport->getKey()),
        );

        // Same order the block presents them in, so the first one is also the
        // club's representative and contact.
        return ($forSport->isNotEmpty() ? $forSport : $club->coaches)
            ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])
            ->values();
    }

    private function representative(?Coach $coach): string
    {
        if (! $coach instanceof Coach) {
            return '';
        }

        return $coach->role ? "{$coach->name}, {$coach->role}" : $coach->name;
    }
}
