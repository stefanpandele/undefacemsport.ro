<?php

use App\Models\Club;
use App\Models\User;

test('a freshly registered user is a consumer', function () {
    $user = User::factory()->create();

    expect($user->isConsumer())->toBeTrue()
        ->and($user->belongsToAnyClub())->toBeFalse()
        ->and($user->ownsAnyClub())->toBeFalse();
});

test('an admin is not a consumer', function () {
    $user = User::factory()->create(['is_admin' => true]);

    expect($user->isConsumer())->toBeFalse();
});

test('a club owner is a representative and master, not a consumer', function () {
    $user = User::factory()->create();
    $club = Club::createForOwner($user, ['name' => 'Clubul Meu', 'slug' => 'clubul-meu']);

    $user->refresh();

    expect($user->isConsumer())->toBeFalse()
        ->and($user->belongsToAnyClub())->toBeTrue()
        ->and($user->ownsAnyClub())->toBeTrue()
        ->and($user->isMasterOf($club))->toBeTrue();
});

test('a non-owner member is a representative but not the master', function () {
    $master = User::factory()->create();
    $club = Club::createForOwner($master, ['name' => 'Alt Club', 'slug' => 'alt-club']);
    $member = User::factory()->create();
    $club->addMember($member);

    $member->refresh();

    expect($member->belongsToAnyClub())->toBeTrue()
        ->and($member->ownsAnyClub())->toBeFalse()
        ->and($member->isMasterOf($club))->toBeFalse()
        ->and($member->isConsumer())->toBeFalse();
});

test('super admin is defined by the email allowlist, not a column', function () {
    config()->set('auth.super_admins', ['boss@undefacemsport.ro']);

    $boss = User::factory()->create(['email' => 'boss@undefacemsport.ro']);
    $other = User::factory()->create(['email' => 'nobody@undefacemsport.ro']);

    expect($boss->isSuperAdmin())->toBeTrue()
        ->and($other->isSuperAdmin())->toBeFalse();
});

test('a super admin bypasses authorization and is never a consumer', function () {
    config()->set('auth.super_admins', ['boss@undefacemsport.ro']);

    $boss = User::factory()->create(['email' => 'boss@undefacemsport.ro', 'is_admin' => false]);
    $regular = User::factory()->create(['email' => 'plain@undefacemsport.ro']);

    expect($boss->can('any-undefined-ability'))->toBeTrue()
        ->and($regular->can('any-undefined-ability'))->toBeFalse()
        ->and($boss->isConsumer())->toBeFalse();
});
