<?php

use App\Models\Club;
use App\Models\User;

test('the user creating a club becomes its owner and first member', function () {
    $user = User::factory()->create();

    $club = Club::createForOwner($user, [
        'name' => 'Club Sportiv Test',
        'slug' => 'club-sportiv-test',
    ]);

    expect($club->owner->is($user))->toBeTrue()
        ->and($club->users->contains($user))->toBeTrue()
        ->and($user->fresh()->ownedClubs->contains($club))->toBeTrue();
});

test('adding another member does not replace the club owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $club = Club::createForOwner($owner, [
        'name' => 'Club Sportiv Test',
        'slug' => 'club-sportiv-test',
    ]);

    $club->addMember($member);

    expect($club->fresh()->owner->is($owner))->toBeTrue()
        ->and($club->users()->whereKey($member)->exists())->toBeTrue();
});

test('the first member added to an existing club becomes its owner', function () {
    $user = User::factory()->create();
    $club = Club::create([
        'name' => 'Club Sportiv Existent',
        'slug' => 'club-sportiv-existent',
    ]);

    $club->addMember($user);

    expect($club->fresh()->owner->is($user))->toBeTrue();
});
