<?php

use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Sport;

test('the public club page renders real sports, coaches and schedule', function () {
    $club = Club::factory()->create(['slug' => 'clubul-test', 'description' => 'Descriere club']);
    $sport = Sport::factory()->create([
        'slug' => 'inot',
        'name' => 'Înot',
        'icon' => '🏊',
        'color' => '#1D7FB8',
    ]);

    $clubSport = $club->clubSports()->create(['sport_id' => $sport->id, 'offers_private_sessions' => true]);
    $clubSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $ageGroup = AgeGroup::factory()->create(['name' => '3–7 ani']);
    $clubSport->ageGroups()->attach($ageGroup);

    $coach = $club->coaches()->create([
        'name' => 'Andrei Popescu',
        'role' => 'Antrenor principal',
        'is_primary' => true,
        'offers_private_sessions' => true,
    ]);
    $coach->sports()->attach($sport);

    $clubLocation = $club->syncLocation(
        ['county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. Bazinului 1', 'name' => 'Bazinul Olimpic'],
        [$sport->id],
    );
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
        'age_group_id' => $ageGroup->id,
        'coach_id' => $coach->id,
    ]);

    $this->get("/cluburi/{$club->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/clubs/Show')
            ->where('club.name', $club->name)
            ->where('club.representative', 'Andrei Popescu, Antrenor principal')
            ->where('club.about', 'Descriere club')
            ->has('club.sports', 1)
            ->where('club.sports.0.key', 'inot')
            ->where('club.sports.0.icon', '🏊')
            ->where('club.sports.0.color', '#1D7FB8')
            ->where('club.sports.0.locationCount', 1)
            ->where('club.sportDetails.inot.icon', '🏊')
            ->has('club.sportDetails.inot.trustChips', 1)
            ->has('club.sportDetails.inot.sessionFormat', 1) // the 1:1 chip, split out from generic benefits
            ->where('club.sportDetails.inot.sessionFormat.0', '🎯 Antrenament 1:1 disponibil')
            ->where('club.sportDetails.inot.ages.0', '3–7 ani')
            ->has('club.coaches', 1)
            ->where('club.coaches.0.name', 'Andrei Popescu')
            ->where('club.coaches.0.solo', true)
            ->where('club.coaches.0.sportIcon', '🏊')
            ->where('club.coaches.0.sportLabel', 'Înot')
            ->has('club.locationsBySport.inot', 1)
            ->where('club.locationsBySport.inot.0.name', 'Bazinul Olimpic')
            ->where('club.locationsBySport.inot.0.schedule.0.day', 'LUN')
            ->has('club.locationsBySport.inot.0.schedule.0.slots', 1)
            ->where('club.locationsBySport.inot.0.schedule.0.slots.0.time', '17:00–18:00')
            ->where('club.locationsBySport.inot.0.schedule.0.slots.0.coach', (string) $coach->id)
        );
});

test('the public club page 404s for an unknown slug', function () {
    $this->get('/cluburi/necunoscut')->assertNotFound();
});
