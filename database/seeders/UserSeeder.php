<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed consumers — public visitors who self-register in the frontend.
     * They have no `is_admin` flag and no club membership. The platform admin
     * is handled by AdminPasswordSeeder; club representatives by OrganizationUserSeeder.
     */
    public function run(): void
    {
        // A known consumer for convenient frontend login testing.
        $demoConsumer = User::updateOrCreate(
            ['email' => 'user@email.com'],
            [
                'name' => 'Vizitator Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Seed the random demo consumers only on the first run, so re-running
        // the seeder does not keep piling up users.
        if ($demoConsumer->wasRecentlyCreated) {
            User::factory()->count(10)->create();
        }
    }
}
