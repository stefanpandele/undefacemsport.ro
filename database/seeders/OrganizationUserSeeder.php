<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationUserSeeder extends Seeder
{
    /**
     * Attach users to clubs. The first member added becomes the club owner
     * (master); the rest are regular representatives. Uses the domain logic
     * in Organization::addMember so the master/member rules are exercised.
     */
    public function run(): void
    {
        $clubs = Organization::all();

        if ($clubs->isEmpty()) {
            $clubs = Organization::factory()->count(3)->create();
        }

        $clubs->each(function (Organization $organization): void {
            // Skip clubs that already have members so re-runs don't duplicate.
            if ($organization->users()->exists()) {
                return;
            }

            // First member becomes the owner (master).
            $organization->addMember(User::factory()->create());

            // Additional representatives (members, not master).
            User::factory()->count(2)->create()->each(
                fn (User $member) => $organization->addMember($member),
            );
        });
    }
}
