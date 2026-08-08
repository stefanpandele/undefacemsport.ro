<?php

use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Organization;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Sport;

test('the organization page renders its courses, people and schedule', function () {
    $organization = Organization::factory()->create(['slug' => 'clubul-test', 'description' => 'Descriere club']);
    $sport = Sport::factory()->create([
        'slug' => 'inot',
        'name' => 'Înot',
        'icon' => '🏊',
        'color' => '#1D7FB8',
    ]);

    $organizationSport = $organization->organizationSports()->create(['sport_id' => $sport->id, 'offers_private_sessions' => true]);
    $organizationSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $ageGroup = AgeGroup::factory()->create(['name' => '3–7 ani']);
    $organizationSport->ageGroups()->attach($ageGroup);

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
