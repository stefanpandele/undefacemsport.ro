<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Models\Club;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    /**
     * How many demo clubs to keep on each plan, so the club panel's
     * subscription limits stay testable and the public explore/location pages
     * have enough volume to look real.
     *
     * Sized against the seeded cities: ClubProfileSeeder places clubs two at a
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
     * Top the demo clubs up to the target mix. They start ownerless;
     * ClubUserSeeder attaches a master and members to them.
     *
     * Tops up rather than bailing out when any club exists, so a database
     * seeded before the mix was widened — or one left half-populated by a
     * failed run — still catches up instead of staying short forever.
     */
    public function run(): void
    {
        $existing = Club::query()
            ->selectRaw('plan, count(*) as total')
            ->groupBy('plan')
            ->pluck('total', 'plan');

        foreach (self::TARGET as $plan => $target) {
            $missing = $target - (int) $existing->get($plan, 0);

            if ($missing < 1) {
                continue;
            }

            Club::factory()->count($missing)->create(['plan' => Plan::from($plan)]);
        }
    }
}
