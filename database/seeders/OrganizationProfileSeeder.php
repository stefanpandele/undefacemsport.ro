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
     * Give every club without a profile a full public presence: sports with
     * benefits and age groups, locations picked from one city, people, a
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

        Organization::query()
            ->doesntHave('organizationSports')
            ->doesntHave('services')
            // Skipped on a re-run: by then the venues have their spaces, and
            // handing them a programme would invent an offer they never made.
            ->doesntHave('spaces')
            ->orderBy('id')
            ->limit($missing)
            ->get()
            ->each(function (Organization $organization, int $index) use ($ageGroups, $levels, $venuesByCity, $cities, $pools): void {
                // Round-robin over cities, two clubs at a time. Handing out one
                // club per city spread them so thin that no city ever held two,
                // and a city with a single club has nobody to share a hall with —
                // which is the whole thing the location page is built to show.
                $city = $cities[intdiv($index, 2) % $cities->count()];
                $venues = $venuesByCity->get($city);
                $pool = $pools->get($city);

                $anchorSport = $pool->first();
                $anchorVenue = $venues->first();

                $organizationSports = $this->addSports($organization, $pool, $anchorSport, $ageGroups, $levels);
                $people = $this->addCoaches($organization, $organizationSports);
                $organizationLocations = $this->addLocations($organization, $venues, $organizationSports, $anchorVenue, $anchorSport);

                $this->addSchedule($organization, $organizationLocations, $people, $ageGroups, $levels);
                $this->addContacts($organization);

                $organization->update([
                    'description' => 'Club sportiv din '.$city.', cu antrenamente pentru copii, juniori și adulți.',
                ]);
            });

        $this->shareHalls($ageGroups, $levels);
    }

    /**
     * @param  Collection<int, Sport>  $pool  the city's sports
     * @param  Collection<int, AgeGroup>  $ageGroups
     * @param  Collection<int, Level>  $levels
     * @return Collection<int, OrganizationSport>
     */
    private function addSports(Organization $organization, Collection $pool, Sport $anchor, Collection $ageGroups, Collection $levels): Collection
    {
        // Premium is unlimited; cap it so the demo stays readable.
        $limit = min($organization->planLimit('sports') ?? 5, 5);

        return collect([$anchor])
            ->concat($pool->reject(fn (Sport $sport): bool => $sport->is($anchor))->shuffle())
            ->take(fake()->numberBetween(1, $limit))
            ->values()
            ->map(function (Sport $sport, int $order) use ($organization, $ageGroups, $levels): OrganizationSport {
                /** @var OrganizationSport $organizationSport */
                $organizationSport = $organization->organizationSports()->create([
                    'sport_id' => $sport->getKey(),
                    'offers_private_sessions' => fake()->boolean(40),
                    'sort_order' => $order,
                ]);

                foreach (fake()->randomElements(self::BENEFITS, fake()->numberBetween(3, 5)) as $position => [$icon, $label]) {
                    $organizationSport->benefits()->create([
                        'icon' => $icon,
                        'label' => $label,
                        'sort_order' => $position,
                    ]);
                }

                $organizationSport->ageGroups()->sync(
                    $ageGroups->shuffle()->take(fake()->numberBetween(1, 3))->pluck('id')->all(),
                );

                // Levels come as a run from the bottom rather than at random: a
                // club that trains competitors almost always also runs a
                // beginners' group, while one that only does initiation has no
                // competitors. Picking freely would produce clubs offering
                // "performanță" and nothing below it, which is not a real club.
                $organizationSport->levels()->sync(
                    $levels->take(fake()->numberBetween(1, min(3, $levels->count())))->pluck('id')->all(),
                );

                return $organizationSport;
            });
    }

    /**
     * A level the club actually teaches for this sport, so a slot never claims a
     * level the club does not offer.
     *
     * @param  Collection<int, Level>  $levels
     */
    private function levelForSport(OrganizationLocationSport $organizationLocationSport, Collection $levels): ?Level
    {
        $organizationSport = $organizationLocationSport->organizationLocation
            ->organization
            ->organizationSports
            ->firstWhere('sport_id', $organizationLocationSport->sport_id);

        $offered = $organizationSport?->levels;

        return $offered === null || $offered->isEmpty()
            ? $levels->first()
            : $offered->random();
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
     * @param  Collection<int, Location>  $venues
     * @param  Collection<int, OrganizationSport>  $organizationSports
     * @return Collection<int, OrganizationLocation>
     */
    private function addLocations(Organization $organization, Collection $venues, Collection $organizationSports, Location $anchorVenue, Sport $anchorSport): Collection
    {
        $limit = min($organization->planLimit('locations') ?? 3, 3, $venues->count());

        // The anchor venue is always taken, so every club in the city ends up
        // in the same hall for the anchor sport.
        return collect([$anchorVenue])
            ->concat($venues->reject(fn (Location $venue): bool => $venue->is($anchorVenue))->shuffle())
            ->take(max(1, $limit))
            ->map(function (Location $venue) use ($organization, $organizationSports, $anchorVenue, $anchorSport): OrganizationLocation {
                // A club rarely teaches every sport at every venue.
                $sportIds = $organizationSports
                    ->shuffle()
                    ->take(fake()->numberBetween(1, $organizationSports->count()))
                    ->pluck('sport_id')
                    ->all();

                if ($venue->is($anchorVenue)) {
                    $sportIds = array_values(array_unique([$anchorSport->getKey(), ...$sportIds]));
                }

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

                foreach ($days as $day) {
                    $blocks = fake()->randomElements(self::TIME_BLOCKS, fake()->numberBetween(1, 2));

                    foreach ($blocks as [$start, $end]) {
                        ScheduleSlot::create([
                            'organization_id' => $organization->getKey(),
                            'organization_location_sport_id' => $organizationLocationSport->getKey(),
                            'day_of_week' => $day,
                            'start_time' => $start,
                            'end_time' => $end,
                            'age_group_id' => $ageGroups->random()->getKey(),
                            'level_id' => $this->levelForSport($organizationLocationSport, $levels)?->getKey(),
                            'person_id' => $eligible->random()->getKey(),
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

        ScheduleSlot::updateOrCreate(
            [
                'organization_location_sport_id' => $organizationLocationSport->getKey(),
                'day_of_week' => $day,
                'start_time' => $start,
            ],
            [
                'organization_id' => $organization->getKey(),
                'end_time' => $end,
                'age_group_id' => $ageGroups->random()->getKey(),
                'level_id' => $this->levelForSport($organizationLocationSport, $levels)?->getKey(),
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
