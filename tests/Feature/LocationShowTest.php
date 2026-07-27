<?php

use App\Enums\ContactType;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubLocationSport;
use App\Models\Facility;
use App\Models\Location;
use App\Models\ScheduleSlot;
use App\Models\Sport;

/**
 * A club teaching one sport at a location, with a coach and one weekly slot.
 */
function swimmingClubAt(string $locationName, Sport $sport, string $clubName): ClubLocationSport
{
    $club = Club::factory()->create(['name' => $clubName, 'description' => 'Despre '.$clubName]);
    $club->contacts()->create(['type' => ContactType::Phone, 'value' => '0722111222']);

    $clubSport = $club->clubSports()->create(['sport_id' => $sport->id, 'offers_private_sessions' => true]);
    $clubSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $clubSport->ageGroups()->attach(AgeGroup::firstOrCreate(['name' => '3–7 ani'], ['sort_order' => 0]));
    $clubSport->images()->create(['path' => 'club-sports/gallery/1.webp', 'collection' => 'gallery']);

    $coach = $club->coaches()->create([
        'name' => 'Andrei Popescu',
        'role' => 'Antrenor principal',
        'is_primary' => true,
        'offers_private_sessions' => true,
    ]);
    $coach->sports()->attach($sport);

    $clubLocation = $club->syncLocation([
        'county' => 'Brașov',
        'city' => 'Brașov',
        'address' => 'Str. Lungă 12',
        'name' => $locationName,
    ], [$sport->id]);

    $clubLocationSport = ClubLocationSport::query()
        ->where('club_location_id', $clubLocation->id)
        ->where('sport_id', $sport->id)
        ->firstOrFail();

    ScheduleSlot::factory()->create([
        'club_id' => $club->id,
        'club_location_sport_id' => $clubLocationSport->id,
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:00',
        'age_group_id' => $clubSport->ageGroups->first()->id,
        'coach_id' => $coach->id,
    ]);

    return $clubLocationSport;
}

test('the location page renders its amenities, sports and club blocks', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot', 'icon' => '🏊', 'color' => '#1D7FB8']);
    $clubLocationSport = swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    $location = $clubLocationSport->clubLocation->location;

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
            ->has('location.clubs.0.coaches', 1)
            ->where('location.clubs.0.schedule.0.day', 'LUN')
            ->has('location.clubs.0.schedule.0.slots', 1)
            ->where('location.clubs.0.schedule.0.slots.0.time', '17:00–18:00')
        );
});

test('each club at the location only shows the schedule it runs there', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $here = swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior');
    $club = $here->clubLocation->club;

    // The same club also trains somewhere else — that must not leak in here.
    $otherLocation = $club->syncLocation([
        'county' => 'Brașov',
        'city' => 'Brașov',
        'address' => 'Str. Scurtă 3',
        'name' => 'Bazinul Mic',
    ], [$sport->id]);

    $otherClubLocationSport = ClubLocationSport::query()
        ->where('club_location_id', $otherLocation->id)
        ->firstOrFail();

    ScheduleSlot::factory()->count(2)->create([
        'club_id' => $club->id,
        'club_location_sport_id' => $otherClubLocationSport->id,
        'day_of_week' => Weekday::Friday,
    ]);

    $this->get('/locatii/'.$here->clubLocation->location->slug)
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
    $location = swimmingClubAt('Bazinul Olimpic', $sport, 'Club Aqua Junior')->clubLocation->location;

    $this->get("/locatii/{$location->slug}?sport=inot")
        ->assertInertia(fn ($page) => $page->where('activeSport', 'inot'));

    // A sport that is not taught here is ignored rather than showing an empty list.
    $this->get("/locatii/{$location->slug}?sport=fotbal")
        ->assertInertia(fn ($page) => $page->where('activeSport', null));
});

test('the location page 404s for an unknown slug', function () {
    $this->get('/locatii/necunoscut')->assertNotFound();
});
