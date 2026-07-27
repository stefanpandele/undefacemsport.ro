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

    Plan::Free->value => [
        'features' => ['gallery'],
        'limits' => [
            'sports' => 1,
            'locations' => 1,
            'gallery_images' => 20,
        ],
    ],

    Plan::Pro->value => [
        'features' => ['gallery', 'sport_covers'],
        'limits' => [
            'sports' => 5,
            'locations' => 3,
            'gallery_images' => 20,
        ],
    ],

    Plan::Premium->value => [
        'features' => ['gallery', 'sport_covers', 'custom_contacts'],
        'limits' => [
            'sports' => null,
            'locations' => null,
            'gallery_images' => 20,
        ],
    ],

];
