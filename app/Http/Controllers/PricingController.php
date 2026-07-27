<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    /**
     * What a club pays, read from the same config the app enforces limits with.
     * A pricing page maintained separately is a pricing page that eventually
     * lies — here the promise and the check share one source.
     */
    public function __invoke(): Response
    {
        return Inertia::render('public/pricing/Index', [
            'plans' => array_map($this->present(...), Plan::cases()),
        ]);
    }

    /**
     * @return array{key: string, name: string, price: int, tagline: string, sports: int|null, locations: int|null, galleryImages: int|null}
     */
    private function present(Plan $plan): array
    {
        return [
            'key' => $plan->value,
            'name' => $plan->label(),
            'price' => (int) config("plans.{$plan->value}.price", 0),
            'tagline' => (string) config("plans.{$plan->value}.tagline", ''),
            // Only the limits the app actually enforces. Advertising anything
            // else would be selling what no code delivers.
            'sports' => $plan->limit('sports'),
            'locations' => $plan->limit('locations'),
            'galleryImages' => $plan->limit('gallery_images'),
        ];
    }
}
