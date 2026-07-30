<?php

use App\Models\Organization;
use App\Models\User;

test('the user creating a club becomes its owner and first member', function () {
    $user = User::factory()->create();

    $organization = Organization::createForOwner($user, [
        'name' => 'Organization Sportiv Test',
        'slug' => 'club-sportiv-test',
    ]);

    expect($organization->owner->is($user))->toBeTrue()
        ->and($organization->users->contains($user))->toBeTrue()
        ->and($user->fresh()->ownedOrganizations->contains($organization))->toBeTrue();
});

test('adding another member does not replace the club owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::createForOwner($owner, [
        'name' => 'Organization Sportiv Test',
        'slug' => 'club-sportiv-test',
    ]);

    $organization->addMember($member);

    expect($organization->fresh()->owner->is($owner))->toBeTrue()
        ->and($organization->users()->whereKey($member)->exists())->toBeTrue();
});

test('the first member added to an existing club becomes its owner', function () {
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Organization Sportiv Existent',
        'slug' => 'club-sportiv-existent',
    ]);

    $organization->addMember($user);

    expect($organization->fresh()->owner->is($user))->toBeTrue();
});

test('ownership can be transferred and the former master stays a member', function () {
    $master = User::factory()->create();
    $organization = Organization::createForOwner($master, [
        'name' => 'Organization Sportiv Test',
        'slug' => 'club-sportiv-test',
    ]);
    $newMaster = User::factory()->create();
    $organization->addMember($newMaster);

    $organization->transferOwnershipTo($newMaster);
    $organization->refresh();

    expect($organization->owner->is($newMaster))->toBeTrue()
        ->and($newMaster->isMasterOf($organization))->toBeTrue()
        ->and($master->isMasterOf($organization))->toBeFalse()
        ->and($organization->users->contains($master))->toBeTrue();
});

test('transferring ownership to a non-member adds them to the club first', function () {
    $master = User::factory()->create();
    $organization = Organization::createForOwner($master, [
        'name' => 'Alt Organization',
        'slug' => 'alt-club',
    ]);
    $newMaster = User::factory()->create();

    $organization->transferOwnershipTo($newMaster);
    $organization->refresh();

    expect($organization->owner->is($newMaster))->toBeTrue()
        ->and($organization->users->contains($newMaster))->toBeTrue();
});
