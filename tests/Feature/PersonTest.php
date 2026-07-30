<?php

use App\Filament\Organization\Resources\People\PersonResource;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Sport;
use App\Models\User;

test('a person belongs to a club and teaches sports', function () {
    $organization = Organization::factory()->create();
    $sports = Sport::factory()->count(2)->create();

    $person = $organization->people()->create([
        'name' => 'Andrei Popescu',
        'role' => 'Antrenor principal',
        'offers_private_sessions' => true,
        'is_primary' => true,
    ]);
    $person->sports()->attach($sports);

    expect($person->organization->is($organization))->toBeTrue()
        ->and($person->sports)->toHaveCount(2)
        ->and($person->offers_private_sessions)->toBeTrue()
        ->and($person->is_primary)->toBeTrue();
});

test('a club exposes its people', function () {
    $organization = Organization::factory()->create();
    Person::factory()->count(3)->create(['organization_id' => $organization->id]);

    expect($organization->people)->toHaveCount(3);
});

test('a club member can open the people page without error', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member)
        ->get(PersonResource::getUrl(panel: 'organization', tenant: $organization))
        ->assertSuccessful();
});
