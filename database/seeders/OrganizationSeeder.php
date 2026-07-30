<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Enums\Plan;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * How many demo clubs to keep on each plan, so the club panel's
     * subscription limits stay testable and the public explore/location pages
     * have enough volume to look real.
     *
     * Sized against the seeded cities: OrganizationProfileSeeder places clubs two at a
     * time, so this has to stay at roughly twice the number of cities in
     * LocationSeeder — below that, cities end up with venues and no clubs, and
     * no two clubs ever share a hall.
     *
     * @var array<string, int>
     */
    private const TARGET = [
        Plan::Free->value => 10,
        Plan::Pro->value => 18,
        Plan::Premium->value => 8,
    ];

    /**
     * Venue organizations: companies that operate a place and sell access to it,
     * rather than running training programmes. Without them nothing would seed a
     * managed space, because every organization until now was a club.
     *
     * On Pro, so each can publish several spaces under its plan limit.
     */
    private const VENUE_TARGET = 10;

    /**
     * Top the demo clubs up to the target mix. They start ownerless;
     * OrganizationUserSeeder attaches a master and members to them.
     *
     * Tops up rather than bailing out when any club exists, so a database
     * seeded before the mix was widened — or one left half-populated by a
     * failed run — still catches up instead of staying short forever.
     */
    public function run(): void
    {
        // Counted per type: a venue also sits on a plan, and letting it count
        // towards the club mix would silently starve the two-clubs-per-city
        // density the location page is built on.
        $existing = Organization::query()
            ->where('type', OrganizationType::Club)
            ->selectRaw('plan, count(*) as total')
            ->groupBy('plan')
            ->pluck('total', 'plan');

        foreach (self::TARGET as $plan => $target) {
            $missing = $target - (int) $existing->get($plan, 0);

            if ($missing < 1) {
                continue;
            }

            Organization::factory()->count($missing)->create(['plan' => Plan::from($plan)]);
        }

        $missingVenues = self::VENUE_TARGET - Organization::query()
            ->where('type', OrganizationType::Venue)
            ->count();

        if ($missingVenues > 0) {
            Organization::factory()->venue()->count($missingVenues)->create(['plan' => Plan::Pro]);
        }
    }
}
