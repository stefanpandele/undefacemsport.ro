<?php

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use Illuminate\Support\Str;

/**
 * A club teaching one sport at a location, with a person and one weekly slot.
 */
function swimmingClubAt(string $locationName, Sport $sport, string $clubName): OrganizationLocationSport
{
    // A slug derived from the name keeps the page's block keys predictable.
    $organization = Organization::factory()->create([
        'name' => $clubName,
        'slug' => Str::slug($clubName),
        'description' => 'Despre '.$clubName,
    ]);
    $organization->contacts()->create(['type' => ContactType::Phone, 'value' => '0722111222']);

    $organizationSport = $organization->organizationSports()->create(['sport_id' => $sport->id]);
    $organizationSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $organizationSport->images()->create(['path' => 'club-sports/gallery/1.webp', 'collection' => 'gallery']);

    $person = $organization->people()->create([
        'name' => 'Andrei Popescu',
        'role' => 'Antrenor principal',
        'is_primary' => true,
    ]);
    $person->sports()->attach($sport, ['offers_private_sessions' => true]);

    $organizationLocation = $organization->syncLocation([
        'county' => 'Brașov',
        'city' => 'Brașov',
        'address' => 'Str. Lungă 12',
        'name' => $locationName,
    ], [$sport->id]);

    $organizationLocationSport = OrganizationLocationSport::query()
        ->where('organization_location_id', $organizationLocation->id)
        ->where('sport_id', $sport->id)
        ->firstOrFail();

    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $organizationLocationSport->id,
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:00',
        'age_group_id' => AgeGroup::firstOrCreate(['name' => '3–7 ani'], ['sort_order' => 0])->id,
        'person_id' => $person->id,
    ]);

    return $organizationLocationSport;
}

test('a coach reaches a location through the hours he teaches there', function () {
    // Polivalentă → baschet → marți 18:00 → Popescu. A coach with the sport
    // ticked but no hour in this hall has never worked here.
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $organization = Organization::factory()->pro()->create();

    $popescu = $organization->people()->create(['name' => 'Popescu', 'role' => 'Antrenor', 'is_primary' => true]);
    $ionescu = $organization->people()->create(['name' => 'Ionescu', 'role' => 'Antrenor']);
    $popescu->sports()->attach($sport);
    $ionescu->sports()->attach($sport);

    $polivalenta = $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Polivalenta'],
        [$sport->getKey()],
    );
    $salaSporturilor = $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Sala Sporturilor'],
        [$sport->getKey()],
    );

    // Popescu teaches at the first hall, Ionescu at the second.
    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $polivalenta->organizationLocationSports->first()->id,
        'day_of_week' => Weekday::Tuesday,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'age_group_id' => AgeGroup::factory()->create()->id,
        'person_id' => $popescu->id,
    ]);
    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $salaSporturilor->organizationLocationSports->first()->id,
        'day_of_week' => Weekday::Thursday,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'age_group_id' => AgeGroup::factory()->create()->id,
        'person_id' => $ionescu->id,
    ]);

    $names = fn (string $slug): array => collect(
        $this->get(route('locations.show', $slug))->viewData('page')['props']['location']['clubs']
    )->flatMap(fn (array $club): array => array_column($club['people'], 'name'))->all();

    expect($names($polivalenta->location->slug))->toBe(['Popescu'])
        ->and($names($salaSporturilor->location->slug))->toBe(['Ionescu']);
});

test('an hour with no coach names nobody rather than the whole staff', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $organization = Organization::factory()->create();

    // A nutritionist on the roster, and nobody assigned to the hour.
    $organization->people()->create(['name' => 'Marin', 'role' => 'Nutriționist']);

    $presence = $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Polivalenta'],
        [$sport->getKey()],
    );

    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $presence->organizationLocationSports->first()->id,
        'day_of_week' => Weekday::Tuesday,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'age_group_id' => AgeGroup::factory()->create()->id,
        'person_id' => null,
    ]);

    $this->get(route('locations.show', $presence->location->slug))->assertInertia(
        fn ($page) => $page
            ->has('location.clubs.0.people', 0)
            ->where('location.clubs.0.representative', '')
            // Still reachable: the club itself answers when no person does.
            ->where('location.clubs.0.contactName', $organization->name),
    );
});

test('a hall shows its own number, and the other one still shows the club\'s', function () {
    // The same coach runs both, but only one hall publishes his line — at the
    // other, the club's switchboard answers.
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $organization = Organization::factory()->pro()->create(['name' => 'CS Delfinul']);
    $organization->contacts()->create(['type' => ContactType::Phone, 'value' => '0722000000']);

    $coach = $organization->people()->create(['name' => 'Andrei Popescu', 'is_primary' => true]);
    $coach->sports()->attach($sport);

    $his = $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A'],
        [$sport->getKey()],
    );
    $theirs = $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Bazinul B'],
        [$sport->getKey()],
    );

    $his->contacts()->create([
        'type' => ContactType::Phone,
        'role' => ContactRole::Person,
        'name' => 'Andrei Popescu',
        'value' => '0722111111',
    ]);

    foreach ([$his, $theirs] as $presence) {
        ScheduleSlot::factory()->create([
            'organization_id' => $organization->id,
            'organization_location_sport_id' => $presence->organizationLocationSports->first()->id,
            'day_of_week' => Weekday::Monday,
            'start_time' => '17:00',
            'end_time' => '18:00',
            'age_group_id' => AgeGroup::factory()->create()->id,
            'person_id' => $coach->id,
        ]);
    }

    $this->get(route('locations.show', $his->location->slug))->assertInertia(
        fn ($page) => $page
            ->where('location.clubs.0.contactPhone', '0722111111')
            ->where('location.clubs.0.contactName', 'Andrei Popescu'),
    );

    $this->get(route('locations.show', $theirs->location->slug))->assertInertia(
        fn ($page) => $page
            ->where('location.clubs.0.contactPhone', '0722000000')
            ->where('location.clubs.0.contactName', 'Andrei Popescu'),
    );
});

test('a hall number with no name on it is answered by the club, not by a coach', function () {
    // The reception picks up: pairing the coach's name with a number that is
    // not his is the mismatch this replaced.
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $organization = Organization::factory()->create(['name' => 'CS Delfinul']);
    $organization->contacts()->create(['type' => ContactType::Phone, 'value' => '0722000000']);

    $coach = $organization->people()->create(['name' => 'Andrei Popescu', 'is_primary' => true]);
    $coach->sports()->attach($sport);

    $presence = $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A'],
        [$sport->getKey()],
    );
    $presence->contacts()->create([
        'type' => ContactType::Phone,
        'role' => ContactRole::General,
        'value' => '0264111111',
    ]);

    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $presence->organizationLocationSports->first()->id,
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:00',
        'age_group_id' => AgeGroup::factory()->create()->id,
        'person_id' => $coach->id,
    ]);

    $this->get(route('locations.show', $presence->location->slug))->assertInertia(
        fn ($page) => $page
            ->where('location.clubs.0.contactPhone', '0264111111')
            ->where('location.clubs.0.contactName', 'CS Delfinul'),
    );
});

test('the location page renders its amenities, sports and club blocks', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot', 'icon' => '🏊', 'color' => '#1D7FB8']);
    $organizationLocationSport = swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    $location = $organizationLocationSport->organizationLocation->location;

    $location->facilities()->attach(Facility::factory()->create(['name' => 'Parcare', 'icon' => '🅿️']));

    $this->get("/locatii/{$location->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/locations/Show')
            ->where('location.name', 'Bazinul Olimpic')
            ->where('location.address', 'Str. Lungă 12, Brașov')
            ->has('location.facilities', 1)
            ->where('location.facilities.0.icon', '🅿️')
            ->where('location.facilities.0.label', 'Parcare')
            ->has('location.sports', 1)
            ->where('location.sports.0.key', 'inot')
            ->where('location.sports.0.icon', '🏊')
            ->where('location.sports.0.color', '#1D7FB8')
            ->where('location.sports.0.clubCount', 1)
            ->has('location.clubs', 1)
            ->where('location.clubs.0.name', 'Club Aqua Junior')
            ->where('location.clubs.0.sport', 'inot')
            ->where('location.clubs.0.representative', 'Andrei Popescu, Antrenor principal')
            ->where('location.clubs.0.contactPhone', '0722111222')
            ->has('location.clubs.0.photos', 1)
            ->has('location.clubs.0.trustChips', 2) // the benefit + the 1:1 chip
            ->where('location.clubs.0.ages.0', '3–7 ani')
            ->has('location.clubs.0.people', 1)
            ->where('location.clubs.0.schedule.0.day', 'LUN')
            ->has('location.clubs.0.schedule.0.slots', 1)
            ->where('location.clubs.0.schedule.0.slots.0.time', '17:00–18:00')
        );
});

test('each club at the location only shows the schedule it runs there', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $here = swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    $organization = $here->organizationLocation->organization;

    // The same club also trains somewhere else — that must not leak in here.
    $otherLocation = $organization->syncLocation([
        'county' => 'Brașov',
        'city' => 'Brașov',
        'address' => 'Str. Scurtă 3',
        'name' => 'Bazinul Mic',
    ], [$sport->id]);

    $otherClubLocationSport = OrganizationLocationSport::query()
        ->where('organization_location_id', $otherLocation->id)
        ->firstOrFail();

    ScheduleSlot::factory()->count(2)->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $otherClubLocationSport->id,
        'day_of_week' => Weekday::Friday,
    ]);

    $this->get('/locatii/'.$here->organizationLocation->location->slug)
        ->assertInertia(fn ($page) => $page
            ->has('location.clubs.0.schedule.0.slots', 1)  // Monday, here
            ->has('location.clubs.0.schedule.4.slots', 0)  // Friday belongs to the other location
        );
});

test('a location with two clubs on the same sport counts both', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    swimmingClubAt('Bazinul Olimpic', $sport, 'Aqua Masters');

    $slug = Location::query()->where('name', 'Bazinul Olimpic')->value('slug');

    $this->get("/locatii/{$slug}")
        ->assertInertia(fn ($page) => $page
            ->has('location.sports', 1)
            ->where('location.sports.0.clubCount', 2)
            ->has('location.clubs', 2)
            ->where('location.clubs.0.name', 'Aqua Masters')
        );
});

test('arriving with a sport filter opens the page on that sport', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $location = swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior')->organizationLocation->location;

    $this->get("/locatii/{$location->slug}?sport=inot")
        ->assertInertia(fn ($page) => $page->where('activeSport', 'inot'));

    // A sport that is not taught here is ignored rather than showing an empty list.
    $this->get("/locatii/{$location->slug}?sport=fotbal")
        ->assertInertia(fn ($page) => $page->where('activeSport', null));
});

test('clubs sharing an interval in the same hall are counted on each other slots', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    swimmingClubAt('Bazinul Olimpic', $sport, 'Aqua Masters');

    $slug = Location::query()->where('name', 'Bazinul Olimpic')->value('slug');

    // Blocks are sorted by club name: 0 = Aqua Masters, 1 = Club Aqua Junior.
    $this->get("/locatii/{$slug}")
        ->assertInertia(fn ($page) => $page
            ->where('location.clubs.0.schedule.0.slots.0.time', '17:00–18:00')
            ->where('location.clubs.0.schedule.0.slots.0.foreign', false)
            // Each entry carries the other club's block key, so the modal can
            // link straight to it further down the page.
            ->where('location.clubs.0.schedule.0.slots.0.otherClubs', [
                ['name' => 'Club Aqua Junior', 'key' => 'club-aqua-junior-inot'],
            ])
            ->where('location.clubs.1.schedule.0.slots.0.otherClubs', [
                ['name' => 'Aqua Masters', 'key' => 'aqua-masters-inot'],
            ])
        );
});

test('an interval only another club trains in shows as an anonymous busy slot', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    $theirs = swimmingClubAt('Bazinul Olimpic', $sport, 'Aqua Masters');

    // Aqua Masters also has the pool on Monday evening; Aqua Junior does not.
    ScheduleSlot::factory()->create([
        'organization_id' => $theirs->organizationLocation->organization_id,
        'organization_location_sport_id' => $theirs->id,
        'day_of_week' => Weekday::Monday,
        'start_time' => '19:00',
        'end_time' => '20:30',
    ]);

    $slug = Location::query()->where('name', 'Bazinul Olimpic')->value('slug');

    $this->get("/locatii/{$slug}")
        ->assertInertia(fn ($page) => $page
            // Aqua Junior sees its own session, then the hall taken at 19:00.
            ->has('location.clubs.1.schedule.0.slots', 2)
            ->where('location.clubs.1.schedule.0.slots.0.foreign', false)
            ->where('location.clubs.1.schedule.0.slots.1.time', '19:00–20:30')
            ->where('location.clubs.1.schedule.0.slots.1.foreign', true)
            ->where('location.clubs.1.schedule.0.slots.1.group', '')
            ->where('location.clubs.1.schedule.0.slots.1.otherClubs', [
                ['name' => 'Aqua Masters', 'key' => 'aqua-masters-inot'],
            ])
            // Aqua Masters owns both intervals, so neither is foreign for it.
            ->where('location.clubs.0.schedule.0.slots.1.foreign', false)
            ->where('location.clubs.0.schedule.0.slots.1.otherClubs', [])
        );
});

test('the clubs sharing an interval are not always listed in the same order', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    // Three clubs on one interval, so a stable order would repeat every time.
    swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    swimmingClubAt('Bazinul Olimpic', $sport, 'Aqua Masters');
    swimmingClubAt('Bazinul Olimpic', $sport, 'Delfinul Brașov');

    $slug = Location::query()->where('name', 'Bazinul Olimpic')->value('slug');

    $orders = collect(range(1, 25))->map(function () use ($slug): string {
        $clubs = $this->get("/locatii/{$slug}")
            ->viewData('page')['props']['location']['clubs'][0]['schedule'][0]['slots'][0]['otherClubs'];

        return collect($clubs)->pluck('name')->implode('|');
    });

    // Whoever registered first must not hold the top spot for everyone.
    expect($orders->unique())->toHaveCount(2);
});

test('a club on a different sport in the same hall is not counted', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $polo = Sport::factory()->create(['slug' => 'polo', 'name' => 'Polo']);

    // Both train Monday 17:00–18:00 in the same pool, on different sports.
    swimmingClubAt('Bazinul Olimpic', $swimming, 'Club Aqua Junior');
    swimmingClubAt('Bazinul Olimpic', $polo, 'Organization Polo Brașov');

    $slug = Location::query()->where('name', 'Bazinul Olimpic')->value('slug');

    $this->get("/locatii/{$slug}")
        ->assertInertia(fn ($page) => $page
            ->has('location.clubs', 2)
            ->where('location.clubs.0.schedule.0.slots.0.otherClubs', [])
            ->where('location.clubs.1.schedule.0.slots.0.otherClubs', [])
            ->has('location.clubs.0.schedule.0.slots', 1)
        );
});

test('the location page 404s for an unknown slug', function () {
    $this->get('/locatii/necunoscut')->assertNotFound();
});
