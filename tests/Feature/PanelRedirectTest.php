<?php

use App\Models\Club;
use App\Models\User;

test('a consumer hitting the admin panel is redirected home', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)->get('/admin')
        ->assertRedirect($consumer->homeUrl());
});

test('a consumer hitting the club panel is redirected home', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)->get('/club')
        ->assertRedirect($consumer->homeUrl());
});

test('an admin hitting the consumer dashboard is redirected home', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertRedirect($admin->homeUrl());
});

test('a club member hitting the admin panel is redirected home', function () {
    $member = User::factory()->create();
    Club::factory()->create()->addMember($member);

    $this->actingAs($member)->get('/admin')
        ->assertRedirect($member->homeUrl());
});

test('a club member hitting the consumer dashboard is redirected home', function () {
    $member = User::factory()->create();
    Club::factory()->create()->addMember($member);

    $this->actingAs($member)->get('/dashboard')
        ->assertRedirect($member->homeUrl());
});

test('a consumer can reach their own dashboard', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)->get('/dashboard')->assertOk();
});
