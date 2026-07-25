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

test('ownership can be transferred and the former master stays a member', function () {
    $master = User::factory()->create();
    $club = Club::createForOwner($master, [
        'name' => 'Club Sportiv Test',
        'slug' => 'club-sportiv-test',
    ]);
    $newMaster = User::factory()->create();
    $club->addMember($newMaster);

    $club->transferOwnershipTo($newMaster);
    $club->refresh();

    expect($club->owner->is($newMaster))->toBeTrue()
        ->and($newMaster->isMasterOf($club))->toBeTrue()
        ->and($master->isMasterOf($club))->toBeFalse()
        ->and($club->users->contains($master))->toBeTrue();
});

test('transferring ownership to a non-member adds them to the club first', function () {
    $master = User::factory()->create();
    $club = Club::createForOwner($master, [
        'name' => 'Alt Club',
        'slug' => 'alt-club',
    ]);
    $newMaster = User::factory()->create();

    $club->transferOwnershipTo($newMaster);
    $club->refresh();

    expect($club->owner->is($newMaster))->toBeTrue()
        ->and($club->users->contains($newMaster))->toBeTrue();
});
