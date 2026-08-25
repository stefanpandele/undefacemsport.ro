<?php

use App\Enums\FacilityStatus;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Organization;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Space;
use App\Models\Sport;

test('the 1:1 chip follows the people, not a tick on the sport', function () {
    $organization = Organization::factory()->pro()->create();
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $basketball = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

    $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A'],
        [$swimming->getKey(), $basketball->getKey()],
    );

    // One coach who takes clients alone, and only for swimming.
    $solo = $organization->people()->create(['name' => 'Ana Ionescu', 'offers_private_sessions' => true]);
    $solo->sports()->attach($swimming);

    $group = $organization->people()->create(['name' => 'Radu Marin', 'offers_private_sessions' => false]);
    $group->sports()->attach($basketball);

    $this->get("/la/{$organization->slug}")->assertInertia(function ($page) {
        $courses = collect($page->toArray()['props']['organization']['courses'])->keyBy('key');

        expect($courses['inot']['sessionFormat'])->toContain('🎯 Antrenament 1:1 disponibil')
            ->and($courses['baschet']['sessionFormat'])->toBe([]);

        return $page;
    });
});

test('the organization page renders its courses, people and schedule', function () {
    $organization = Organization::factory()->create(['slug' => 'clubul-test', 'description' => 'Descriere club']);
    $sport = Sport::factory()->create([
        'slug' => 'inot',
        'name' => 'Înot',
        'icon' => '🏊',
        'color' => '#1D7FB8',
    ]);

    $organizationSport = $organization->organizationSports()->create(['sport_id' => $sport->id]);
    $organizationSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $ageGroup = AgeGroup::factory()->create(['name' => '3–7 ani']);

    $person = $organization->people()->create([
        'name' => 'Andrei Popescu',
        'role' => 'Antrenor principal',
        'is_primary' => true,
        'offers_private_sessions' => true,
    ]);
    $person->sports()->attach($sport);

    $organizationLocation = $organization->syncLocation(
        ['county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. Bazinului 1', 'name' => 'Bazinul Olimpic'],
        [$sport->id],
    );
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
        'age_group_id' => $ageGroup->id,
        'person_id' => $person->id,
    ]);

    $this->get("/la/{$organization->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/organizations/Show')
            ->where('organization.name', $organization->name)
            ->where('organization.representative', 'Andrei Popescu, Antrenor principal')
            ->where('organization.about', 'Descriere club')
            ->has('organization.courses', 1)
            ->where('organization.courses.0.key', 'inot')
            ->where('organization.courses.0.icon', '🏊')
            ->where('organization.courses.0.color', '#1D7FB8')
            ->has('organization.courses.0.locations', 1)
            ->where('organization.courses.0.icon', '🏊')
            ->has('organization.courses.0.trustChips', 1)
            ->has('organization.courses.0.sessionFormat', 1) // the 1:1 chip, split out from generic benefits
            ->where('organization.courses.0.sessionFormat.0', '🎯 Antrenament 1:1 disponibil')
            ->where('organization.courses.0.ages.0', '3–7 ani')
            ->has('organization.people', 1)
            ->where('organization.people.0.name', 'Andrei Popescu')
            ->where('organization.people.0.solo', true)
            ->where('organization.people.0.sportIcon', '🏊')
            ->where('organization.people.0.sportLabel', 'Înot')
            ->has('organization.courses.0.locations', 1)
            ->where('organization.courses.0.locations.0.name', 'Bazinul Olimpic')
            ->where('organization.courses.0.locations.0.schedule.0.day', 'LUN')
            ->has('organization.courses.0.locations.0.schedule.0.slots', 1)
            ->where('organization.courses.0.locations.0.schedule.0.slots.0.time', '17:00–18:00')
            ->where('organization.courses.0.locations.0.schedule.0.slots.0.coach', (string) $person->id)
            // Hall occupancy is a location-page concern; the club's own profile
            // never shows who else trains there, but shares the slot shape.
            ->where('organization.courses.0.locations.0.schedule.0.slots.0.foreign', false)
            ->where('organization.courses.0.locations.0.schedule.0.slots.0.otherClubs', [])
        );
});

test('the organization page 404s for an unknown slug', function () {
    $this->get('/la/necunoscut')->assertNotFound();
});

test('the addresses these pages used to live at still work', function () {
    $organization = Organization::factory()->create(['slug' => 'aqua-junior']);

    $this->get('/cluburi/aqua-junior')->assertRedirect('/la/aqua-junior');
    $this->get('/specialisti/aqua-junior')->assertRedirect('/la/aqua-junior');
});

test('the page opens on where, county by county', function () {
    // An organization with halls in two counties cannot be read as one list of
    // offers: nobody attends a course two counties away.
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot', 'icon' => '🏊']);
    $organization = Organization::factory()->create(['slug' => 'multi-judet']);
    $organization->organizationSports()->create(['sport_id' => $sport->id]);

    $organization->syncLocation(
        ['county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. A 1', 'name' => 'Bazinul Brașov'],
        [$sport->id],
    );
    $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Bazinul Cluj'],
        [$sport->id],
    );

    $this->get('/la/multi-judet')->assertInertia(fn ($page) => $page
        ->has('organization.counties', 2)
        // In the order the presences were added, not alphabetical.
        ->where('organization.counties.0.label', 'Brașov')
        ->where('organization.counties.1.label', 'Cluj')
        ->has('organization.counties.0.locations', 1)
        ->where('organization.counties.0.locations.0.name', 'Bazinul Brașov')
        ->where('organization.counties.0.locations.0.sports.0.key', 'inot')
        ->where('organization.counties.0.locations.0.sports.0.ways.0.key', 'cursuri')
    );
});

test('a sport reached only through a space still shows up at its address', function () {
    // The pool that sells tickets without teaching: the sport is here because a
    // space is here, not because anybody runs a programme.
    $sport = Sport::factory()->create(['slug' => 'tenis', 'name' => 'Tenis']);
    $organization = Organization::factory()->create(['slug' => 'baza-tenis']);

    $presence = $organization->syncLocation(
        ['county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. C 3', 'name' => 'Baza Tenis'],
    );

    Space::factory()->rental()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'sport_id' => $sport->getKey(),
        'status' => FacilityStatus::Approved,
    ]);

    $this->get('/la/baza-tenis')->assertInertia(fn ($page) => $page
        ->has('organization.counties.0.locations.0.sports', 1)
        ->where('organization.counties.0.locations.0.sports.0.key', 'tenis')
        ->where('organization.counties.0.locations.0.sports.0.ways.0.key', 'inchiriere')
    );
});

test('a sauna is an extra at its address, never a sport', function () {
    $organization = Organization::factory()->create(['slug' => 'cu-sauna']);
    $presence = $organization->syncLocation(
        ['county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. D 4', 'name' => 'Baza cu saună'],
    );

    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'sport_id' => null,
        'name' => 'Saună',
        'status' => FacilityStatus::Approved,
    ]);

    $this->get('/la/cu-sauna')->assertInertia(fn ($page) => $page
        ->where('organization.counties.0.locations.0.sports', [])
        ->has('organization.counties.0.locations.0.extras', 1)
        ->where('organization.counties.0.locations.0.extras.0.name', 'Saună')
    );
});

test('a club that also rents out a space still renders its timetable', function () {
    // A tariff is credited to the organization that wrote it, so not every slot a
    // club owns is a training. Reading one as a training killed the page for any
    // club that rents out its dead hours — which is the whole point of letting it.
    $organization = Organization::factory()->create(['slug' => 'clubul-cu-sala']);
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

    $organization->organizationSports()->create(['sport_id' => $sport->id]);

    $presence = $organization->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A'],
        [$sport->id],
    );

    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $presence->organizationLocationSports->first()->id,
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:30',
    ]);

    $hall = Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'sport_id' => $sport->id,
    ]);

    // The tariff the club wrote for its own hall, credited to the club.
    ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Access,
        'organization_id' => $organization->id,
        'space_id' => $hall->getKey(),
        'access_mode' => SpaceAccessMode::ExclusiveRental,
        'price' => 100,
        'day_of_week' => Weekday::Tuesday,
        'start_time' => '20:00',
        'end_time' => '22:00',
    ]);

    $this->get('/la/clubul-cu-sala')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Monday holds the training…
            ->has('organization.courses.0.locations.0.schedule.0.slots', 1)
            // …and Tuesday holds nothing, because a tariff is not a training.
            ->has('organization.courses.0.locations.0.schedule.1.slots', 0));
});
