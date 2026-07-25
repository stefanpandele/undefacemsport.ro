<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClubAccessSeeder extends Seeder
{
    /**
     * A known club representative (club@email.com / password) who owns a demo
     * Pro club, for easy /club panel login in local dev. Parallels the admin
     * seeders (super admin via allowlist, adminl via AdminAccessSeeder).
     */
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'club@email.com'],
            [
                'name' => 'Reprezentant Club Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $club = Club::firstWhere('slug', 'clubul-demo')
            ?? Club::factory()->pro()->create([
                'name' => 'Clubul Demo',
                'slug' => 'clubul-demo',
            ]);

        $club->addMember($owner);
    }
}
