<?php

use App\Filament\Club\Resources\Coaches\CoachResource;
use App\Models\Club;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\User;

test('a coach belongs to a club and teaches sports', function () {
    $club = Club::factory()->create();
    $sports = Sport::factory()->count(2)->create();

    $coach = $club->coaches()->create([
        'name' => 'Andrei Popescu',
        'role' => 'Antrenor principal',
        'offers_private_sessions' => true,
        'is_primary' => true,
    ]);
    $coach->sports()->attach($sports);

    expect($coach->club->is($club))->toBeTrue()
        ->and($coach->sports)->toHaveCount(2)
        ->and($coach->offers_private_sessions)->toBeTrue()
        ->and($coach->is_primary)->toBeTrue();
});

test('a club exposes its coaches', function () {
    $club = Club::factory()->create();
    Coach::factory()->count(3)->create(['club_id' => $club->id]);

    expect($club->coaches)->toHaveCount(3);
});

test('a club member can open the coaches page without error', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    $club->addMember($member);

    $this->actingAs($member)
        ->get(CoachResource::getUrl(panel: 'club', tenant: $club))
        ->assertSuccessful();
});
