<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminPasswordSeeder extends Seeder
{
    public function run(): void
    {
        // In production the admin password must come from the ADMIN_PASSWORD
        // env var; locally it falls back to a convenient default. Never seed a
        // production admin with a weak, source-controlled password.
        $password = env('ADMIN_PASSWORD', app()->isProduction() ? null : 'password');

        if (blank($password)) {
            $this->command->warn('ADMIN_PASSWORD is not set — skipping admin user seed.');

            return;
        }

        // updateOrCreate keeps the seeder idempotent: re-running updates the
        // existing admin instead of hitting the unique email constraint.
        User::updateOrCreate(
            ['email' => 'stefan@nmo.ro'],
            [
                'name' => 'Stefan P.',
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
