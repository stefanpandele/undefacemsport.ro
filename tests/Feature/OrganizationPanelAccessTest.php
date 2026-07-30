<?php

use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use App\Models\Organization;
use App\Models\User;

test('a club member can open the club sports page without error', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member)
        ->get(OrganizationSportResource::getUrl(panel: 'organization', tenant: $organization))
        ->assertSuccessful();
});
