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

        if ($sports->isEmpty() || $venuesByCity->isEmpty()) {
            return;
        }

        $cities = $venuesByCity->keys();

        Club::query()
            ->doesntHave('clubSports')
            ->get()
            ->each(function (Club $club, int $index) use ($sports, $ageGroups, $venuesByCity, $cities): void {
                // Round-robin over cities so every city gets clubs, instead of
                // the random draw clustering them all in one place.
                $city = $cities[$index % $cities->count()];

                $clubSports = $this->addSports($club, $sports, $ageGroups);
                $coaches = $this->addCoaches($club, $clubSports);
                $clubLocations = $this->addLocations($club, $venuesByCity->get($city), $clubSports);

                $this->addSchedule($club, $clubLocations, $coaches, $ageGroups);
                $this->addContacts($club);

                $club->update([
                    'description' => 'Club sportiv din '.$city.', cu antrenamente pentru copii, juniori și adulți.',
                ]);
            });
    }

    /**
     * @param  Collection<int, Sport>  $sports
     * @param  Collection<int, AgeGroup>  $ageGroups
     * @return Collection<int, ClubSport>
     */
    private function addSports(Club $club, Collection $sports, Collection $ageGroups): Collection
    {
        // Premium is unlimited; cap it so the demo stays readable.
        $limit = min($club->planLimit('sports') ?? 5, 5);

        return $sports
            ->shuffle()
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
    private function addLocations(Club $club, Collection $venues, Collection $clubSports): Collection
    {
        $limit = min($club->planLimit('locations') ?? 3, 3, $venues->count());

        return $venues
            ->shuffle()
            ->take(max(1, $limit))
            ->map(function (Location $venue) use ($club, $clubSports): ClubLocation {
                // A club rarely teaches every sport at every venue.
                $sportIds = $clubSports
                    ->shuffle()
                    ->take(fake()->numberBetween(1, $clubSports->count()))
                    ->pluck('sport_id')
                    ->all();

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
