<?php

namespace Database\Seeders;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubLocation;
use App\Models\ClubLocationSport;
use App\Models\ClubSport;
use App\Models\Coach;
use App\Models\Location;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ClubProfileSeeder extends Seeder
{
    /**
     * Romanian names, so the demo data reads like the real thing — the global
     * faker locale is en_US and is shared with every other factory.
     *
     * @var list<string>
     */
    private const FIRST_NAMES = [
        'Andrei', 'Mihai', 'Elena', 'Ioana', 'Cristian', 'Maria', 'Radu', 'Alexandru',
        'Daniela', 'Bogdan', 'Ana', 'Vlad', 'Raluca', 'Ștefan', 'Gabriela', 'Cătălin',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Popescu', 'Ionescu', 'Dumitru', 'Radu', 'Marinescu', 'Georgescu', 'Stan',
        'Constantinescu', 'Munteanu', 'Barbu', 'Vasile', 'Nistor', 'Toma', 'Iliescu',
    ];

    /** @var list<string> */
    private const ROLES = [
        'Antrenor principal', 'Antrenor', 'Antrenoare', 'Coordonator', 'Instructor',
    ];

    /** @var list<string> */
    private const BIOS = [
        'Licențiat federal, cu experiență în lucrul pe grupe de copii.',
        'Fost sportiv de performanță, acum dedicat pregătirii juniorilor.',
        'Specializat în inițiere și tehnică de bază, în grupe mici.',
        'Pregătire pentru competiții regionale și naționale.',
        'Lucrează cu adulți, de la recuperare la menținere.',
    ];

    /**
     * Trust chips clubs show per sport.
     *
     * @var list<array{string, string}>
     */
    private const BENEFITS = [
        ['🏅', 'Antrenori licențiați'],
        ['📅', 'Peste 5 ani de activitate'],
        ['👥', '100+ sportivi înscriși'],
        ['🏆', 'Medalii la campionate naționale'],
        ['🌱', 'Grupe pentru începători'],
        ['🚌', 'Transport la competiții'],
        ['📝', 'Prima ședință gratuită'],
        ['🧑‍🏫', 'Grupe mici, maximum 10 sportivi'],
        ['💪', 'Pregătire fizică specifică'],
        ['♿', 'Grupe adaptate'],
    ];

    /**
     * Training intervals a slot can occupy, kept non-overlapping per day.
     *
     * @var list<array{string, string}>
     */
    private const TIME_BLOCKS = [
        ['09:00', '10:00'],
        ['10:00', '11:30'],
        ['16:00', '17:00'],
        ['17:00', '18:30'],
        ['18:30', '20:00'],
        ['20:00', '21:30'],
    ];

    /**
     * Give every club without a profile a full public presence: sports with
     * benefits and age groups, locations picked from one city, coaches, a
     * weekly schedule and contacts.
     *
     * Idempotent by construction: a club that already offers a sport is left
     * alone, so re-running never doubles a club's profile.
     */
    public function run(): void
    {
        $sports = Sport::query()->get();
        $ageGroups = AgeGroup::query()->get();
        $venuesByCity = Location::query()->get()->groupBy('city');

        if ($sports->isEmpty() || $ageGroups->isEmpty() || $venuesByCity->isEmpty()) {
            return;
        }

        $cities = $venuesByCity->keys();

        // Each city runs on a handful of sports rather than all twenty, and one
        // of them is the city's anchor: every club here teaches it, at the same
        // anchor venue. That is what makes clubs actually share a hall, which
        // the location page's occupancy view exists to show.
        $pools = $cities->mapWithKeys(fn (string $city): array => [
            $city => $sports->shuffle()->take(min(6, $sports->count()))->values(),
        ]);

        Club::query()
            ->doesntHave('clubSports')
            ->get()
            ->each(function (Club $club, int $index) use ($ageGroups, $venuesByCity, $cities, $pools): void {
                // Round-robin over cities so every city gets clubs, instead of
                // the random draw clustering them all in one place.
                $city = $cities[$index % $cities->count()];
                $venues = $venuesByCity->get($city);
                $pool = $pools->get($city);

                $anchorSport = $pool->first();
                $anchorVenue = $venues->first();

                $clubSports = $this->addSports($club, $pool, $anchorSport, $ageGroups);
                $coaches = $this->addCoaches($club, $clubSports);
                $clubLocations = $this->addLocations($club, $venues, $clubSports, $anchorVenue, $anchorSport);

                $this->addSchedule($club, $clubLocations, $coaches, $ageGroups);
                $this->addContacts($club);

                $club->update([
                    'description' => 'Club sportiv din '.$city.', cu antrenamente pentru copii, juniori și adulți.',
                ]);
            });

        $this->shareHalls($ageGroups);
    }

    /**
     * @param  Collection<int, Sport>  $pool  the city's sports
     * @param  Collection<int, AgeGroup>  $ageGroups
     * @return Collection<int, ClubSport>
     */
    private function addSports(Club $club, Collection $pool, Sport $anchor, Collection $ageGroups): Collection
    {
        // Premium is unlimited; cap it so the demo stays readable.
        $limit = min($club->planLimit('sports') ?? 5, 5);

        return collect([$anchor])
            ->concat($pool->reject(fn (Sport $sport): bool => $sport->is($anchor))->shuffle())
            ->take(fake()->numberBetween(1, $limit))
            ->values()
            ->map(function (Sport $sport, int $order) use ($club, $ageGroups): ClubSport {
                /** @var ClubSport $clubSport */
                $clubSport = $club->clubSports()->create([
                    'sport_id' => $sport->getKey(),
                    'offers_private_sessions' => fake()->boolean(40),
                    'sort_order' => $order,
                ]);

                foreach (fake()->randomElements(self::BENEFITS, fake()->numberBetween(3, 5)) as $position => [$icon, $label]) {
                    $clubSport->benefits()->create([
                        'icon' => $icon,
                        'label' => $label,
                        'sort_order' => $position,
                    ]);
                }

                $clubSport->ageGroups()->sync(
                    $ageGroups->shuffle()->take(fake()->numberBetween(1, 3))->modelKeys(),
                );

                return $clubSport;
            });
    }

    /**
     * @param  Collection<int, ClubSport>  $clubSports
     * @return Collection<int, Coach>
     */
    private function addCoaches(Club $club, Collection $clubSports): Collection
    {
        $count = min($clubSports->count() + fake()->numberBetween(0, 2), 4);

        return collect(range(1, max(1, $count)))
            ->map(function (int $position) use ($club, $clubSports): Coach {
                /** @var Coach $coach */
                $coach = $club->coaches()->create([
                    'name' => fake()->randomElement(self::FIRST_NAMES).' '.fake()->randomElement(self::LAST_NAMES),
                    'role' => $position === 1 ? 'Antrenor principal' : fake()->randomElement(self::ROLES),
                    'bio' => fake()->randomElement(self::BIOS),
                    'offers_private_sessions' => fake()->boolean(35),
                    'is_primary' => $position === 1,
                    'sort_order' => $position - 1,
                ]);

                // Each coach teaches one or two of the club's own sports.
                $coach->sports()->sync(
                    $clubSports->shuffle()
                        ->take(fake()->numberBetween(1, min(2, $clubSports->count())))
                        ->pluck('sport_id')
                        ->all(),
                );

                return $coach;
            });
    }

    /**
     * @param  Collection<int, Location>  $venues
     * @param  Collection<int, ClubSport>  $clubSports
     * @return Collection<int, ClubLocation>
     */
    private function addLocations(Club $club, Collection $venues, Collection $clubSports, Location $anchorVenue, Sport $anchorSport): Collection
    {
        $limit = min($club->planLimit('locations') ?? 3, 3, $venues->count());

        // The anchor venue is always taken, so every club in the city ends up
        // in the same hall for the anchor sport.
        return collect([$anchorVenue])
            ->concat($venues->reject(fn (Location $venue): bool => $venue->is($anchorVenue))->shuffle())
            ->take(max(1, $limit))
            ->map(function (Location $venue) use ($club, $clubSports, $anchorVenue, $anchorSport): ClubLocation {
                // A club rarely teaches every sport at every venue.
                $sportIds = $clubSports
                    ->shuffle()
                    ->take(fake()->numberBetween(1, $clubSports->count()))
                    ->pluck('sport_id')
                    ->all();

                if ($venue->is($anchorVenue)) {
                    $sportIds = array_values(array_unique([$anchorSport->getKey(), ...$sportIds]));
                }

                return $club->syncLocation([
                    'county' => $venue->county,
                    'city' => $venue->city,
                    'address' => $venue->address,
                    'name' => $venue->name,
                    'latitude' => $venue->latitude,
                    'longitude' => $venue->longitude,
                ], $sportIds);
            })
            ->values();
    }

    /**
     * A weekly schedule for every sport the club teaches at every one of its
     * locations, on two or three days, without overlapping intervals.
     *
     * @param  Collection<int, ClubLocation>  $clubLocations
     * @param  Collection<int, Coach>  $coaches
     * @param  Collection<int, AgeGroup>  $ageGroups
     */
    private function addSchedule(Club $club, Collection $clubLocations, Collection $coaches, Collection $ageGroups): void
    {
        $clubLocations
            ->flatMap(fn (ClubLocation $clubLocation) => $clubLocation->clubLocationSports)
            ->each(function (ClubLocationSport $clubLocationSport) use ($club, $coaches, $ageGroups): void {
                $days = collect(Weekday::cases())
                    ->shuffle()
                    ->take(fake()->numberBetween(2, 3));

                // Coaches who actually teach this sport, so the schedule does
                // not credit a swimming coach with a basketball session.
                $eligible = $coaches->filter(
                    fn (Coach $coach): bool => $coach->sports->contains('id', $clubLocationSport->sport_id),
                );
                $eligible = $eligible->isEmpty() ? $coaches : $eligible;

                foreach ($days as $day) {
                    $blocks = fake()->randomElements(self::TIME_BLOCKS, fake()->numberBetween(1, 2));

                    foreach ($blocks as [$start, $end]) {
                        ScheduleSlot::create([
                            'club_id' => $club->getKey(),
                            'club_location_sport_id' => $clubLocationSport->getKey(),
                            'day_of_week' => $day,
                            'start_time' => $start,
                            'end_time' => $end,
                            'age_group_id' => $ageGroups->random()->getKey(),
                            'coach_id' => $eligible->random()->getKey(),
                        ]);
                    }
                }
            });
    }

    /**
     * Make the shared-hall case explicit instead of hoping the random draw
     * produces it. Wherever two clubs teach the same sport at the same venue:
     *
     *  - they all get one identical interval, which renders as the
     *    "+X cluburi" badge on each club's own slot;
     *  - one of them gets a second interval nobody else has, which the other
     *    clubs see as the anonymous "hall is taken" card.
     *
     * Keyed by (club_location_sport, day, start), so re-running is a no-op.
     *
     * @param  Collection<int, AgeGroup>  $ageGroups
     */
    private function shareHalls(Collection $ageGroups): void
    {
        ClubLocationSport::query()
            ->with('clubLocation.club.coaches')
            ->get()
            ->groupBy(fn (ClubLocationSport $clubLocationSport): string => $clubLocationSport->clubLocation->location_id.'-'.$clubLocationSport->sport_id)
            ->filter(fn (Collection $sharing): bool => $sharing->count() > 1)
            ->each(function (Collection $sharing) use ($ageGroups): void {
                foreach ($sharing as $clubLocationSport) {
                    $this->slotAt($clubLocationSport, Weekday::Wednesday, '18:00', '19:30', $ageGroups);
                }

                // One club alone in the hall later that evening.
                $this->slotAt($sharing->first(), Weekday::Wednesday, '20:00', '21:30', $ageGroups);
            });
    }

    /**
     * @param  Collection<int, AgeGroup>  $ageGroups
     */
    private function slotAt(ClubLocationSport $clubLocationSport, Weekday $day, string $start, string $end, Collection $ageGroups): void
    {
        $club = $clubLocationSport->clubLocation->club;

        $coach = $club->coaches->first(
            fn (Coach $candidate): bool => $candidate->sports->contains('id', $clubLocationSport->sport_id),
        ) ?? $club->coaches->first();

        ScheduleSlot::updateOrCreate(
            [
                'club_location_sport_id' => $clubLocationSport->getKey(),
                'day_of_week' => $day,
                'start_time' => $start,
            ],
            [
                'club_id' => $club->getKey(),
                'end_time' => $end,
                'age_group_id' => $ageGroups->random()->getKey(),
                'coach_id' => $coach?->getKey(),
            ],
        );
    }

    private function addContacts(Club $club): void
    {
        $handle = str($club->slug)->limit(20, '')->toString();

        $club->contacts()->create([
            'type' => ContactType::Phone,
            'role' => ContactRole::General,
            'value' => '07'.fake()->numerify('########'),
            'sort_order' => 0,
        ]);

        $club->contacts()->create([
            'type' => ContactType::Email,
            'role' => ContactRole::General,
            'value' => 'contact@'.$handle.'.ro',
            'sort_order' => 1,
        ]);

        if (fake()->boolean(70)) {
            $club->contacts()->create([
                'type' => ContactType::Instagram,
                'role' => ContactRole::General,
                'value' => 'https://instagram.com/'.$handle,
                'sort_order' => 2,
            ]);
        }

        if (fake()->boolean(50)) {
            $club->contacts()->create([
                'type' => ContactType::Website,
                'role' => ContactRole::General,
                'value' => 'https://'.$handle.'.ro',
                'sort_order' => 3,
            ]);
        }
    }
}
