<?php

namespace Database\Seeders;

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
        // 18 for the programmes, 18 more for the spaces and services the later
        // seeders hand out — an organization on Pro can publish several of
        // either under its plan limit.
        Plan::Pro->value => 36,
        Plan::Premium->value => 8,
    ];

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
        // All of them, on every plan. What each one turns into is decided later,
        // by whichever seeder hands it an offer — none of them is anything yet.
        $existing = Organization::query()
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
    }
}
