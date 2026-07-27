<?php

use App\Enums\Plan;

test('the pricing page shows the plans the app actually enforces', function () {
    $this->get('/preturi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/pricing/Index')
            ->has('plans', 3)
            ->where('plans.0.key', 'free')
            ->where('plans.0.price', 0)
            ->where('plans.0.sports', 1)
            ->where('plans.0.locations', 1)
            ->where('plans.1.key', 'pro')
            ->where('plans.1.sports', 5)
            ->where('plans.1.locations', 3)
            // Unlimited comes through as null, which the page words itself.
            ->where('plans.2.key', 'premium')
            ->where('plans.2.sports', null)
            ->where('plans.2.locations', null)
        );
});

test('what the page advertises is what a club is held to', function () {
    // The page reads the same config as canAddSport()/canAddLocation(), so a
    // change to one is a change to both. Prove they cannot drift.
    config(['plans.pro.limits.sports' => 42]);

    $this->get('/preturi')
        ->assertInertia(fn ($page) => $page->where('plans.1.sports', 42));

    expect(Plan::Pro->limit('sports'))->toBe(42);
});

test('every plan may show the same number of gallery photos', function () {
    $this->get('/preturi')
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.galleryImages', 20)
            ->where('plans.1.galleryImages', 20)
            ->where('plans.2.galleryImages', 20)
        );
});
