<?php

use App\Models\Organization;
use App\Models\User;

test('a consumer hitting the admin panel is redirected home', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)->get('/admin')
        ->assertRedirect($consumer->homeUrl());
});

test('a consumer hitting the organization panel is redirected home', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)->get('/cont')
        ->assertRedirect($consumer->homeUrl());
});

test('the old /club panel URLs still lead somewhere', function () {
    // The panel moved to /cont once it started serving venues and practices too.
    // Bookmarks and links shared before that must not die.
    $this->get('/club')->assertStatus(301)->assertRedirect('/cont');
    $this->get('/club/some-tenant/locations')->assertStatus(301)->assertRedirect('/cont/some-tenant/locations');
});

test('an admin hitting the consumer dashboard is redirected home', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertRedirect($admin->homeUrl());
});

test('a club member hitting the admin panel is redirected home', function () {
    $member = User::factory()->create();
    Organization::factory()->create()->addMember($member);

    $this->actingAs($member)->get('/admin')
        ->assertRedirect($member->homeUrl());
});

test('a club member hitting the consumer dashboard is redirected home', function () {
    $member = User::factory()->create();
    Organization::factory()->create()->addMember($member);

    $this->actingAs($member)->get('/dashboard')
        ->assertRedirect($member->homeUrl());
});

test('a consumer can reach their own dashboard', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)->get('/dashboard')->assertOk();
});
