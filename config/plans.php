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

    Plan::Free->value => [
        'features' => [],
        'limits' => [
            'sports' => 1,
            'locations' => 1,
            'gallery_images' => 0,
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
            'gallery_images' => null,
        ],
    ],

];
