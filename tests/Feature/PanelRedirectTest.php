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

test('the panel answers at /cont and nowhere else', function (string $legacy) {
    // The panel lives at /cont since it started serving venues and practices too.
    // A catch-all under /club used to redirect here, but it redirected to a
    // relative path: the browser resolved `cont/login` against /club/, landed on
    // /club/cont/login, matched the same catch-all, and grew one `cont` per hop
    // until the URL bar was a wall of them. There is nothing left to redirect —
    // /club must simply not resolve.
    $this->get($legacy)->assertNotFound();
})->with(['/club', '/club/login', '/club/some-tenant/locations']);

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
