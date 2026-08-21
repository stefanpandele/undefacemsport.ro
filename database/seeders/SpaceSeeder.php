<?php

namespace Database\Seeders;

use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\ScheduleSlot;
use App\Models\Space;
use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class SpaceSeeder extends Seeder
{
    /**
     * How many of the leftover organizations become venues, each publishing two
     * or three spaces under its plan limit.
     */
    private const VENUE_TARGET = 10;

    /**
     * The kinds of thing a venue actually sells, with the shape of each: a pool
     * charges per entry all day, a padel court is booked by the hour, an open-gym
     * runs a couple of evenings a week.
     *
     * `sport` is a slug or null — a sauna is not a sport.
     *
     * @var list<array{name: string, sport: string|null, mode: string, price: int, unit: string, morning_price: int|null, capacity: int|null, indoor: bool, days: list<int>|null, start: string, end: string, open_gym?: array{days: list<int>, start: string, end: string, price: int}}>
     */
    private const VENUE_SPACES = [
        [
            'name' => 'Bazin de înot', 'sport' => 'inot', 'mode' => 'open_access',
            'price' => 45, 'unit' => 'entry', 'morning_price' => 35, 'capacity' => 6,
            'indoor' => true, 'days' => null, 'start' => '07:00', 'end' => '22:00',
        ],
        [
            'name' => 'Sală fitness', 'sport' => 'fitness', 'mode' => 'open_access',
            'price' => 60, 'unit' => 'entry', 'morning_price' => null, 'capacity' => null,
            'indoor' => true, 'days' => null, 'start' => '07:00', 'end' => '22:00',
        ],
        [
            'name' => 'Saună', 'sport' => null, 'mode' => 'open_access',
            'price' => 50, 'unit' => 'entry', 'morning_price' => null, 'capacity' => 8,
            'indoor' => true, 'days' => null, 'start' => '12:00', 'end' => '22:00',
        ],
        [
            'name' => 'Teren 1', 'sport' => 'padel', 'mode' => 'exclusive_rental',
            'price' => 120, 'unit' => 'hour', 'morning_price' => 90, 'capacity' => 4,
            'indoor' => false, 'days' => null, 'start' => '08:00', 'end' => '23:00',
        ],
        [
            'name' => 'Teren 2', 'sport' => 'padel', 'mode' => 'exclusive_rental',
            'price' => 140, 'unit' => 'hour', 'morning_price' => null, 'capacity' => 4,
            'indoor' => true, 'days' => null, 'start' => '08:00', 'end' => '23:00',
        ],
        [
            'name' => 'Teren mare', 'sport' => 'fotbal', 'mode' => 'exclusive_rental',
            'price' => 200, 'unit' => 'hour', 'morning_price' => 150, 'capacity' => 22,
            'indoor' => false, 'days' => null, 'start' => '08:00', 'end' => '23:00',
        ],
        [
            // One hall, two ways in: booked whole by the hour most of the week,
            // open-gym on Friday and Sunday evenings. The exception that the
            // per-interval access mode exists for.
            'name' => 'Sala mare de baschet', 'sport' => 'baschet', 'mode' => 'exclusive_rental',
            'price' => 180, 'unit' => 'hour', 'morning_price' => null, 'capacity' => 12,
            'indoor' => true, 'days' => null, 'start' => '08:00', 'end' => '22:00',
            'open_gym' => ['days' => [Weekday::Friday->value, Weekday::Sunday->value], 'start' => '20:00', 'end' => '22:00', 'price' => 25],
        ],
    ];

    /**
     * Free, unmanaged spaces — the ones nobody will ever register, and the reason
     * `spaces.organization_location_id` is nullable.
     *
     * @var list<array{name: string, sport: string, indoor: bool, floodlights: bool, verified_months_ago: int|null}>
     */
    private const PUBLIC_SPACES = [
        ['name' => 'Teren de baschet', 'sport' => 'baschet', 'indoor' => false, 'floodlights' => true, 'verified_months_ago' => 1],
        ['name' => 'Teren de baschet 2', 'sport' => 'baschet', 'indoor' => false, 'floodlights' => false, 'verified_months_ago' => 8],
        ['name' => 'Teren de fotbal cu gazon sintetic', 'sport' => 'fotbal', 'indoor' => false, 'floodlights' => true, 'verified_months_ago' => 2],
        ['name' => 'Masă de tenis', 'sport' => 'tenis-de-masa', 'indoor' => false, 'floodlights' => false, 'verified_months_ago' => null],
        ['name' => 'Teren de volei pe nisip', 'sport' => 'volei', 'indoor' => false, 'floodlights' => false, 'verified_months_ago' => 14],
    ];

    /**
     * Give the seeded venues something to sell, and put a handful of free public
     * spaces on the map beside them.
     *
     * Deliberately ordered rather than random: which venue gets which space has to
     * come out the same on every run, or re-seeding piles a second set of spaces
     * on top of the first.
     */
    public function run(): void
    {
        $sports = Sport::query()->pluck('id', 'slug');
        // Organizations still without an offer of their own: OrganizationProfileSeeder
        // has already handed out the programmes, so what is left reads as a pure
        // venue once it gets spaces.
        $missing = self::VENUE_TARGET - Organization::query()
            ->has('spaces')
            ->doesntHave('organizationSports')
            ->count();

        $venues = $missing < 1 ? collect() : Organization::query()
            ->doesntHave('organizationSports')
            ->doesntHave('services')
            ->doesntHave('spaces')
            ->orderBy('id')
            ->limit($missing)
            ->get();
        $locations = Location::query()->orderBy('id')->get();

        if ($locations->isEmpty()) {
            return;
        }

        $this->seedVenueSpaces($venues, $locations, $sports);
        $this->seedPublicSpaces($locations, $sports);
    }

    /**
     * Each venue claims one location and publishes two or three spaces there.
     *
     * @param  Collection<int, Organization>  $venues
     * @param  Collection<int, Location>  $locations
     * @param  Collection<string, int>  $sports
     */
    private function seedVenueSpaces(Collection $venues, Collection $locations, Collection $sports): void
    {
        foreach ($venues as $index => $venue) {
            $location = $locations[$index % $locations->count()];

            $presence = OrganizationLocation::firstOrCreate([
                'organization_id' => $venue->getKey(),
                'location_id' => $location->getKey(),
            ]);

            // Two or three spaces per venue, walking the catalogue so the mix is
            // varied but reproducible.
            foreach (range(0, 1 + ($index % 2)) as $offset) {
                $blueprint = self::VENUE_SPACES[($index * 2 + $offset) % count(self::VENUE_SPACES)];

                $this->createSpace($presence->location_id, $presence->getKey(), $blueprint, $sports, $venue->getKey());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  Collection<string, int>  $sports
     */
    private function createSpace(int $locationId, ?int $presenceId, array $blueprint, Collection $sports, ?int $organizationId): void
    {
        $space = Space::firstOrCreate(
            [
                'location_id' => $locationId,
                'name' => $blueprint['name'],
            ],
            [
                'organization_location_id' => $presenceId,
                'sport_id' => $blueprint['sport'] === null ? null : $sports->get($blueprint['sport']),
                'capacity' => $blueprint['capacity'],
                'is_indoor' => $blueprint['indoor'],
            ],
        );

        if (! $space->wasRecentlyCreated) {
            return;
        }

        $mode = SpaceAccessMode::from($blueprint['mode']);
        $unit = PriceUnit::from($blueprint['unit']);

        $days = $blueprint['days'] === null
            ? Weekday::cases()
            : array_map(fn (int $day): Weekday => Weekday::from($day), $blueprint['days']);

        foreach ($days as $day) {
            // A cheaper morning where the venue has one, which is what makes the
            // public page say "de la 35 lei" instead of quoting the afternoon rate.
            if ($blueprint['morning_price'] !== null) {
                $this->slot($space, $organizationId, $day, $blueprint['start'], '12:00', $blueprint['morning_price'], $mode, $unit);
                $this->slot($space, $organizationId, $day, '12:00', $blueprint['end'], $blueprint['price'], $mode, $unit);

                continue;
            }

            $this->slot($space, $organizationId, $day, $blueprint['start'], $blueprint['end'], $blueprint['price'], $mode, $unit);
        }

        if (isset($blueprint['open_gym'])) {
            $this->addOpenGym($space, $organizationId, $blueprint['open_gym']);
        }
    }

    /**
     * A second way into the same hall: the evenings it is open per person rather
     * than booked whole. Overrides both the mode and the unit, because 25 lei a
     * head and 180 lei an hour are not the same number in different clothes.
     *
     * @param  array{days: list<int>, start: string, end: string, price: int}  $openGym
     */
    private function addOpenGym(Space $space, ?int $organizationId, array $openGym): void
    {
        foreach ($openGym['days'] as $day) {
            ScheduleSlot::updateOrCreate(
                [
                    'kind' => ScheduleSlotKind::Access,
                    'space_id' => $space->getKey(),
                    'day_of_week' => Weekday::from($day),
                    'start_time' => $openGym['start'],
                ],
                [
                    'organization_id' => $organizationId,
                    'end_time' => $openGym['end'],
                    'price' => $openGym['price'],
                    'access_mode' => SpaceAccessMode::OpenAccess,
                    'price_unit' => PriceUnit::Entry,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @param  Collection<string, int>  $sports
     */
    private function seedPublicSpaces(Collection $locations, Collection $sports): void
    {
        foreach (self::PUBLIC_SPACES as $index => $blueprint) {
            $sportId = $sports->get($blueprint['sport']);

            if ($sportId === null) {
                continue;
            }

            // Spread across locations from the far end, so public spaces do not
            // all land at the same addresses the venues took.
            $location = $locations[($locations->count() - 1 - $index + $locations->count()) % $locations->count()];

            $space = Space::firstOrCreate(
                [
                    'location_id' => $location->getKey(),
                    'name' => $blueprint['name'],
                ],
                [
                    'organization_location_id' => null,
                    'sport_id' => $sportId,
                    'is_indoor' => $blueprint['indoor'],
                    'has_floodlights' => $blueprint['floodlights'],
                ],
            );

            if (! $space->wasRecentlyCreated) {
                continue;
            }

            $space->forceFill([
                'last_verified_at' => $blueprint['verified_months_ago'] === null
                    ? null
                    : now()->subMonths($blueprint['verified_months_ago']),
            ])->save();

            // Dawn to dusk, every day, free. Nobody locks a park.
            foreach (Weekday::cases() as $day) {
                $this->slot($space, null, $day, '07:00', $blueprint['floodlights'] ? '22:00' : '20:00', 0);
            }
        }
    }

    private function slot(
        Space $space,
        ?int $organizationId,
        Weekday $day,
        string $start,
        string $end,
        ?float $price,
        SpaceAccessMode $mode = SpaceAccessMode::OpenAccess,
        ?PriceUnit $unit = null,
    ): void {
        ScheduleSlot::updateOrCreate(
            [
                'kind' => ScheduleSlotKind::Access,
                'space_id' => $space->getKey(),
                'day_of_week' => $day,
                'start_time' => $start,
            ],
            [
                'organization_id' => $organizationId,
                'end_time' => $end,
                'price' => $price,
                'access_mode' => $mode,
                'price_unit' => $unit,
            ],
        );
    }
}
