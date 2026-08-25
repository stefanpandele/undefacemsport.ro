<?php

namespace Database\Seeders;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Level;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\OrganizationLocationSport;
use App\Models\OrganizationSport;
use App\Models\Person;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class OrganizationProfileSeeder extends Seeder
{
    /**
     * The groups and levels each address teaches, drawn on first use so every
     * hour there agrees with the ones beside it.
     *
     * @var array<int, array{ages: Collection<int, AgeGroup>, levels: Collection<int, Level>}>
     */
    private array $offerByLocationSport = [];

    /**
     * How many of the seeded organizations get a training programme — the demo
     * mix plus the two known login accounts. The rest stay blank for the seeders
     * that follow, so the fixture ends up with venues and practices rather than
     * clubs that also happen to rent out a hall.
     */
    private const CLUB_TARGET = 38;

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
     * How many counties a club works in, and how many sports it teaches at each
     * of its addresses.
     *
     * A club present in one town tells you nothing about the page that has to
     * hold three: the county navigator, the per-location sport lists and the
     * schedules under them are all invisible in a fixture where every club sits
     * at one address teaching one thing.
     *
     * Free stays at one and one, because that is what Free *is* — "un club cu un
     * singur sport, la o singură sală" is the plan's own promise, and a fixture
     * that broke it would be showing something the app forbids.
     */
    private const COUNTY_TARGET = 3;

    private const SPORTS_PER_LOCATION = 3;

    /**
     * A club big enough for a fourth address gets its second one in the town it
     * already works in — two halls in the same city is the commonest real shape,
     * and it is the only way the county navigator ever shows more than one card
     * under a county.
     *
     * Only Premium reaches it: Pro buys three addresses, and three counties uses
     * all of them.
     */
    private const SECOND_IN_HOME_CITY = self::COUNTY_TARGET + 1;

    /**
     * Give every club without a profile a full public presence: sports with
     * benefits and age groups, addresses across several counties, people, a
     * weekly schedule and contacts.
     *
     * Idempotent by construction: a club that already offers a sport is left
     * alone, so re-running never doubles a club's profile.
     */
    public function run(): void
    {
        $sports = Sport::query()->get();
        $ageGroups = AgeGroup::query()->get();
        $levels = Level::query()->orderBy('sort_order')->get();
        /** @var Collection<string, Collection<int, Location>> $venuesByCity */
        $venuesByCity = Location::query()
            ->whereNotNull('county')
            ->get()
            ->groupBy('city')
            ->map(fn ($venues): Collection => collect($venues->all()));

        if ($sports->isEmpty() || $ageGroups->isEmpty() || $venuesByCity->isEmpty()) {
            return;
        }

        // Ordered by how many halls a town has, most first. The clubs are
        // ordered to match below, so the ones whose plan buys a fourth address
        // land where a second hall exists — two halls in one town is the case
        // Premium is in the fixture to show, and until now it held only by luck
        // of index arithmetic: splitting București into sectors broke it.
        $cities = $venuesByCity
            ->keys()
            ->sortByDesc(fn (string $city): int => $venuesByCity->get($city)->count())
            ->values();

        // Each city runs on a handful of sports rather than all twenty, and one
        // of them is its anchor: every club working here teaches it, at the same
        // anchor venue. That is what makes clubs actually share a hall, which
        // the location page's occupancy view exists to show.
        /** @var Collection<string, Collection<int, Sport>> $pools */
        $pools = $cities->mapWithKeys(fn (string $city): array => [
            $city => collect($sports->shuffle()->take(min(6, $sports->count()))->values()->all()),
        ]);

        // Anchored per city rather than per county: București alone spans several
        // of them, and anchoring a whole county would leave its other cities with
        // a single club and nobody to share a hall with.
        $anchors = $cities->mapWithKeys(fn (string $city): array => [
            $city => [
                'sport' => $pools->get($city)->first(),
                'venue' => $venuesByCity->get($city)->first(),
            ],
        ]);

        // Whatever is still blank, up to the club target. What is left over goes
        // to SpaceSeeder and PracticeSeeder, which run after this one — an
        // organization becomes a club by being handed a programme, not by having
        // been marked one.
        // Topped up rather than taken: OrganizationAccessSeeder has already given
        // the showcase studio its sport, and counting from zero here would push
        // the fixture one club over every time.
        $missing = self::CLUB_TARGET - Organization::query()->has('organizationSports')->count();

        if ($missing < 1) {
            return;
        }

        $this->blankOrganizations($missing)
            ->each(function (Organization $organization, int $index) use ($ageGroups, $levels, $cities, $pools, $anchors, $venuesByCity): void {
                // Round-robin over counties, two clubs at a time. Handing out one
                // club per county spread them so thin that no hall ever held two,
                // and a club alone in a hall shares it with nobody — which is the
                // whole thing the location page is built to show.
                $home = (string) $cities[intdiv($index, 2) % $cities->count()];
                $reach = $this->reachOf($organization, $home, $anchors);
                $venues = $this->venuesFor($organization, $home, $reach, $anchors, $venuesByCity);

                $organizationSports = $this->addSports($organization, $reach, $pools, $anchors);
                $people = $this->addCoaches($organization, $organizationSports);
                $organizationLocations = $this->addLocations($organization, $venues, $anchors, $organizationSports);

                $this->addSchedule($organization, $organizationLocations, $people, $ageGroups, $levels);
                $this->addContacts($organization);

                $organization->update([
                    'description' => $reach->count() > 1
                        ? 'Club sportiv cu antrenamente în '.$reach->implode(', ').', pentru copii, juniori și adulți.'
                        : 'Club sportiv din '.$home.', cu antrenamente pentru copii, juniori și adulți.',
                ]);
            });

        $this->shareHalls($ageGroups, $levels);
    }

    /**
     * The organizations still without an offer, spread across the plans.
     *
     * Taken in plain id order they came out of one plan at a time: the fixture
     * creates Free first, then Pro, then Premium, so a straight `limit` handed
     * every club profile to the two cheapest tiers and left Premium — the only
     * plan that can hold a fourth address — with none at all.
     *
     * @return Collection<int, Organization>
     */
    private function blankOrganizations(int $missing): Collection
    {
        $byPlan = Organization::query()
            ->doesntHave('organizationSports')
            ->doesntHave('services')
            // Skipped on a re-run: by then the venues have their spaces, and
            // handing them a programme would invent an offer they never made.
            ->doesntHave('spaces')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Organization $organization): string => $organization->plan->value)
            ->map(fn (Collection $organizations): Collection => $organizations->values());

        /** @var Collection<int, Organization> $spread */
        $spread = collect();

        for ($round = 0; $spread->count() < $missing; $round++) {
            $taken = 0;

            foreach ($byPlan as $organizations) {
                $organization = $organizations->get($round);

                if ($organization === null) {
                    continue;
                }

                $taken++;

                if ($spread->count() < $missing) {
                    $spread->push($organization);
                }
            }

            if ($taken === 0) {
                break;
            }
        }

        // Clubs that can take a fourth address first, so they meet the towns
        // with the most halls at the head of the rotation. Same set, different
        // order — the spread across plans above is what keeps the mix honest.
        return $spread
            ->sortByDesc(fn (Organization $organization): int => $organization->planLimit('locations') ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * The cities a club works in: its home one, then one per further county.
     *
     * Distinct counties on purpose. Three addresses in the same town would fill
     * the fixture without ever producing the page this exists to show — the one
     * that has to ask which county you are asking about before it can answer.
     *
     * @param  Collection<string, array{sport: Sport, venue: Location}>  $anchors
     * @return Collection<int, string>
     */
    private function reachOf(Organization $organization, string $home, Collection $anchors): Collection
    {
        // Premium is unlimited; capped so the demo stays readable.
        $limit = min($organization->planLimit('locations') ?? self::COUNTY_TARGET, self::COUNTY_TARGET);

        $taken = collect([(string) $anchors->get($home)['venue']->county]);

        // The city names are shuffled, not the map: `shuffle()` reindexes, and a
        // shuffled map hands back integers where a city name is expected.
        $elsewhere = $anchors->keys()
            ->reject(fn (string $city): bool => $city === $home)
            ->shuffle()
            ->filter(function (string $city) use ($anchors, $taken): bool {
                $county = (string) $anchors->get($city)['venue']->county;

                if ($taken->contains($county)) {
                    return false;
                }

                $taken->push($county);

                return true;
            });

        return collect([$home])
            ->concat($elsewhere)
            ->take(max(1, $limit))
            ->values();
    }

    /**
     * The sports a club teaches: the anchor of every county it works in, then
     * filled up so every one of its addresses can carry three.
     *
     * @param  Collection<int, string>  $reach  cities
     * @param  Collection<string, Collection<int, Sport>>  $pools  keyed by city
     * @param  Collection<string, array{sport: Sport, venue: Location}>  $anchors
     * @return Collection<int, OrganizationSport>
     */
    private function addSports(Organization $organization, Collection $reach, Collection $pools, Collection $anchors): Collection
    {
        // Premium is unlimited; cap it so the demo stays readable.
        $limit = min($organization->planLimit('sports') ?? 5, 5);

        /** @var Collection<int, Sport> $anchorSports */
        $anchorSports = $reach->map(fn (string $city): Sport => $anchors->get($city)['sport'])->unique('id');

        /** @var Collection<int, Sport> $filler */
        $filler = $reach
            ->flatMap(fn (string $city): Collection => $pools->get($city))
            ->unique('id')
            ->reject(fn (Sport $sport): bool => $anchorSports->contains('id', $sport->getKey()))
            ->shuffle();

        // At least three where the plan allows three: every address carries that
        // many, and an address cannot teach a sport the club does not.
        $wanted = max(min(self::SPORTS_PER_LOCATION, $limit), $anchorSports->count());

        return $anchorSports
            ->concat($filler)
            ->take(min($limit, max($wanted, fake()->numberBetween($wanted, $limit))))
            ->values()
            ->map(function (Sport $sport, int $order) use ($organization): OrganizationSport {
                /** @var OrganizationSport $organizationSport */
                $organizationSport = $organization->organizationSports()->create([
                    'sport_id' => $sport->getKey(),
                    'sort_order' => $order,
                ]);

                foreach (fake()->randomElements(self::BENEFITS, fake()->numberBetween(3, 5)) as $position => [$icon, $label]) {
                    $organizationSport->benefits()->create([
                        'icon' => $icon,
                        'label' => $label,
                        'sort_order' => $position,
                    ]);
                }

                return $organizationSport;
            });
    }

    /**
     * The halls a club takes: the anchor of every city it reaches, and — where
     * the plan runs to a fourth address — a second hall in its home town.
     *
     * @param  Collection<int, string>  $reach  cities
     * @param  Collection<string, array{sport: Sport, venue: Location}>  $anchors
     * @param  Collection<string, Collection<int, Location>>  $venuesByCity
     * @return Collection<int, Location>
     */
    private function venuesFor(Organization $organization, string $home, Collection $reach, Collection $anchors, Collection $venuesByCity): Collection
    {
        $venues = $reach->map(fn (string $city): Location => $anchors->get($city)['venue']);

        $limit = $organization->planLimit('locations');

        if ($limit !== null && $limit < self::SECOND_IN_HOME_CITY) {
            return $venues->values();
        }

        $second = $venuesByCity->get($home, collect())
            ->reject(fn (Location $venue): bool => $venue->is($anchors->get($home)['venue']))
            ->shuffle()
            ->first();

        return ($second === null ? $venues : $venues->push($second))->values();
    }

    /**
     * One presence per hall, each teaching its city's anchor plus enough of the
     * club's other sports to reach three.
     *
     * @param  Collection<int, Location>  $venues
     * @param  Collection<string, array{sport: Sport, venue: Location}>  $anchors
     * @param  Collection<int, OrganizationSport>  $organizationSports
     * @return Collection<int, OrganizationLocation>
     */
    private function addLocations(Organization $organization, Collection $venues, Collection $anchors, Collection $organizationSports): Collection
    {
        return $venues
            ->map(function (Location $venue) use ($organization, $anchors, $organizationSports): OrganizationLocation {
                $anchor = $anchors->get((string) $venue->city);

                // The anchor first, so every club working in this city ends up in
                // the same hall for it — then filled to three, or to whatever
                // the club has if it teaches fewer.
                $sportIds = collect([$anchor['sport']->getKey()])
                    ->concat($organizationSports
                        ->pluck('sport_id')
                        ->reject(fn (int $id): bool => $id === $anchor['sport']->getKey())
                        ->shuffle())
                    ->unique()
                    ->take(max(self::SPORTS_PER_LOCATION, 1))
                    ->values()
                    ->all();

                return $organization->syncLocation([
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
     * What one address teaches: a subset of the groups and a run of levels,
     * drawn once and reused by every hour there.
     *
     * Drawn per address rather than per club, because that is the whole point of
     * reading groups off the hours — a club with a children's pool and an
     * adults' pool has to come out of the seeder looking like one.
     *
     * Levels come as a run from the bottom rather than at random: a club that
     * trains competitors almost always also runs a beginners' group, while one
     * that only does initiation has no competitors. Picking freely would produce
     * clubs offering "performanță" and nothing below it, which is not a real club.
     *
     * @param  Collection<int, AgeGroup>  $ageGroups
     * @param  Collection<int, Level>  $levels  in vocabulary order, easiest first
     * @param  int  $hours  how many slots there are to spread it over
     * @return array{ages: Collection<int, AgeGroup>, levels: Collection<int, Level>}
     */
    private function offerAt(OrganizationLocationSport $organizationLocationSport, Collection $ageGroups, Collection $levels, int $hours = 1): array
    {
        $spread = max(1, min(3, $hours));

        return $this->offerByLocationSport[$organizationLocationSport->getKey()] ??= [
            'ages' => $ageGroups->shuffle()->take(fake()->numberBetween(1, $spread))->values(),
            'levels' => $levels->take(fake()->numberBetween(1, min($spread, $levels->count())))->values(),
        ];
    }

    /**
     * @param  Collection<int, OrganizationSport>  $organizationSports
     * @return Collection<int, Person>
     */
    private function addCoaches(Organization $organization, Collection $organizationSports): Collection
    {
        $count = min($organizationSports->count() + fake()->numberBetween(0, 2), 4);

        return collect(range(1, max(1, $count)))
            ->map(function (int $position) use ($organization, $organizationSports): Person {
                /** @var Person $person */
                $person = $organization->people()->create([
                    'name' => fake()->randomElement(self::FIRST_NAMES).' '.fake()->randomElement(self::LAST_NAMES),
                    'role' => $position === 1 ? 'Antrenor principal' : fake()->randomElement(self::ROLES),
                    'bio' => fake()->randomElement(self::BIOS),
                    'offers_private_sessions' => fake()->boolean(35),
                    'is_primary' => $position === 1,
                    'sort_order' => $position - 1,
                ]);

                // Each person teaches one or two of the club's own sports.
                $person->sports()->sync(
                    $organizationSports->shuffle()
                        ->take(fake()->numberBetween(1, min(2, $organizationSports->count())))
                        ->pluck('sport_id')
                        ->all(),
                );

                return $person;
            });
    }

    /**
     * A weekly schedule for every sport the club teaches at every one of its
     * locations, on two or three days, without overlapping intervals.
     *
     * @param  Collection<int, OrganizationLocation>  $organizationLocations
     * @param  Collection<int, Person>  $people
     * @param  Collection<int, AgeGroup>  $ageGroups
     * @param  Collection<int, Level>  $levels
     */
    private function addSchedule(Organization $organization, Collection $organizationLocations, Collection $people, Collection $ageGroups, Collection $levels): void
    {
        $organizationLocations
            ->flatMap(fn (OrganizationLocation $organizationLocation) => $organizationLocation->organizationLocationSports)
            ->each(function (OrganizationLocationSport $organizationLocationSport) use ($organization, $people, $ageGroups, $levels): void {
                $days = collect(Weekday::cases())
                    ->shuffle()
                    ->take(fake()->numberBetween(2, 3));

                // People who actually teach this sport, so the schedule does
                // not credit a swimming person with a basketball session.
                $eligible = $people->filter(
                    fn (Person $person): bool => $person->sports->contains('id', $organizationLocationSport->sport_id),
                );
                $eligible = $eligible->isEmpty() ? $people : $eligible;

                /** @var list<array{Weekday, string, string}> $hours */
                $hours = [];

                foreach ($days as $day) {
                    foreach (fake()->randomElements(self::TIME_BLOCKS, fake()->numberBetween(1, 2)) as [$start, $end]) {
                        $hours[] = [$day, $start, $end];
                    }
                }

                $offer = $this->offerAt($organizationLocationSport, $ageGroups, $levels, count($hours));

                // Dealt round-robin rather than drawn per hour: what the address
                // teaches is now read back off these rows, so a level nobody was
                // given never existed. Drawing freely left addresses whose hours
                // happened to skip the bottom of the run.
                foreach ($hours as $index => [$day, $start, $end]) {
                    ScheduleSlot::create([
                        'organization_id' => $organization->getKey(),
                        'organization_location_sport_id' => $organizationLocationSport->getKey(),
                        'day_of_week' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'age_group_id' => $offer['ages'][$index % $offer['ages']->count()]->getKey(),
                        'level_id' => $offer['levels'][$index % $offer['levels']->count()]->getKey(),
                        'person_id' => $eligible->random()->getKey(),
                    ]);
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
     * Keyed by (organization_location_sport, day, start), so re-running is a no-op.
     *
     * @param  Collection<int, AgeGroup>  $ageGroups
     * @param  Collection<int, Level>  $levels
     */
    private function shareHalls(Collection $ageGroups, Collection $levels): void
    {
        OrganizationLocationSport::query()
            ->with('organizationLocation.organization.people')
            ->get()
            ->groupBy(fn (OrganizationLocationSport $organizationLocationSport): string => $organizationLocationSport->organizationLocation->location_id.'-'.$organizationLocationSport->sport_id)
            ->filter(fn (Collection $sharing): bool => $sharing->count() > 1)
            ->each(function (Collection $sharing) use ($ageGroups, $levels): void {
                foreach ($sharing as $organizationLocationSport) {
                    $this->slotAt($organizationLocationSport, Weekday::Wednesday, '18:00', '19:30', $ageGroups, $levels);
                }

                // One club alone in the hall later that evening.
                $this->slotAt($sharing->first(), Weekday::Wednesday, '20:00', '21:30', $ageGroups, $levels);
            });
    }

    /**
     * @param  Collection<int, AgeGroup>  $ageGroups
     * @param  Collection<int, Level>  $levels
     */
    private function slotAt(OrganizationLocationSport $organizationLocationSport, Weekday $day, string $start, string $end, Collection $ageGroups, Collection $levels): void
    {
        $organization = $organizationLocationSport->organizationLocation->organization;

        $person = $organization->people->first(
            fn (Person $candidate): bool => $candidate->sports->contains('id', $organizationLocationSport->sport_id),
        ) ?? $organization->people->first();

        $offer = $this->offerAt($organizationLocationSport, $ageGroups, $levels);

        ScheduleSlot::updateOrCreate(
            [
                'organization_location_sport_id' => $organizationLocationSport->getKey(),
                'day_of_week' => $day,
                'start_time' => $start,
            ],
            [
                'organization_id' => $organization->getKey(),
                'end_time' => $end,
                'age_group_id' => $offer['ages']->random()->getKey(),
                'level_id' => $offer['levels']->random()->getKey(),
                'person_id' => $person?->getKey(),
            ],
        );
    }

    private function addContacts(Organization $organization): void
    {
        $handle = str($organization->slug)->limit(20, '')->toString();

        $organization->contacts()->create([
            'type' => ContactType::Phone,
            'role' => ContactRole::General,
            'value' => '07'.fake()->numerify('########'),
            'sort_order' => 0,
        ]);

        $organization->contacts()->create([
            'type' => ContactType::Email,
            'role' => ContactRole::General,
            'value' => 'contact@'.$handle.'.ro',
            'sort_order' => 1,
        ]);

        if (fake()->boolean(70)) {
            $organization->contacts()->create([
                'type' => ContactType::Instagram,
                'role' => ContactRole::General,
                'value' => 'https://instagram.com/'.$handle,
                'sort_order' => 2,
            ]);
        }

        if (fake()->boolean(50)) {
            $organization->contacts()->create([
                'type' => ContactType::Website,
                'role' => ContactRole::General,
                'value' => 'https://'.$handle.'.ro',
                'sort_order' => 3,
            ]);
        }
    }
}
