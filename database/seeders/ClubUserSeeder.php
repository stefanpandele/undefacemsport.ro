<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClubUserSeeder extends Seeder
{
    /**
     * Attach users to clubs. The first member added becomes the club owner
     * (master); the rest are regular representatives. Uses the domain logic
     * in Club::addMember so the master/member rules are exercised.
     */
    public function run(): void
    {
        $clubs = Club::all();

        if ($clubs->isEmpty()) {
            $clubs = Club::factory()->count(3)->create();
        }

        $clubs->each(function (Club $club): void {
            // Skip clubs that already have members so re-runs don't duplicate.
            if ($club->users()->exists()) {
                return;
            }

            // First member becomes the owner (master).
            $club->addMember(User::factory()->create());

            // Additional representatives (members, not master).
            User::factory()->count(2)->create()->each(
                fn (User $member) => $club->addMember($member),
            );
        });
    }
}
