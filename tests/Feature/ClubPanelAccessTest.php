<?php

use App\Filament\Club\Resources\ClubSports\ClubSportResource;
use App\Models\Club;
use App\Models\User;

test('a club member can open the club sports page without error', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    $club->addMember($member);

    $this->actingAs($member)
        ->get(ClubSportResource::getUrl(panel: 'club', tenant: $club))
        ->assertSuccessful();
});
