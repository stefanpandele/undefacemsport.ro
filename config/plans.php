<?php

use App\Enums\Plan;

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Feature & limit map per club subscription plan, keyed by the Plan enum
    | value. A `null` limit means unlimited; an omitted limit key resolves to 0
    | (not available). These are placeholder tiers — tune them to your billing.
    |
    */

    /*
    | `gallery_images` is deliberately the same on every plan. How well a club
    | can present itself to a visitor is not for sale: two clubs side by side in
    | the same hall must be able to show the same number of photos. Plans differ
    | on how much a club may list (sports, locations), never on how good the
    | listing looks.
    */

    /*
    | `price` is monthly, in RON, and feeds the public /preturi page directly —
    | so what a club is told it pays and what the app enforces can never drift
    | apart. PLACEHOLDERS until real billing exists.
    */

    /*
    | `spaces` counts offers, not rows: one sport at one address, however many
    | courts stand there. A padel club names every court because indoor and
    | outdoor are a real choice, and charging each of them would price the honest
    | page higher than a vague one — then show a visitor five courts out of
    | eleven, which is not a smaller page but a false one.
    */

    Plan::Free->value => [
        'price' => 0,
        'tagline' => 'Pentru un club cu un singur sport, la o singură sală.',
        'features' => ['gallery'],
        'limits' => [
            'sports' => 1,
            'locations' => 1,
            'spaces' => 1,
            'services' => 3,
            'gallery_images' => 20,
        ],
    ],

    Plan::Pro->value => [
        'price' => 99,
        'tagline' => 'Pentru cluburile care predau mai multe sporturi, în mai multe locuri.',
        'features' => ['gallery', 'sport_covers'],
        'limits' => [
            'sports' => 5,
            'locations' => 3,
            'spaces' => 5,
            'services' => 15,
            'gallery_images' => 20,
        ],
    ],

    Plan::Premium->value => [
        'price' => 199,
        'tagline' => 'Pentru cluburile mari, fără limite pe sporturi sau locații.',
        'features' => ['gallery', 'sport_covers', 'custom_contacts'],
        'limits' => [
            'sports' => null,
            'locations' => null,
            'spaces' => null,
            'services' => null,
            'gallery_images' => 20,
        ],
    ],

];
