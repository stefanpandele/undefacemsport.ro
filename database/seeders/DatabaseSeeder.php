<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment() !== 'production') {
            $this->call([
                AdminPasswordSeeder::class,
                SportSeeder::class,
                CountySeeder::class,
                LocalitySeeder::class,
                ClubSeeder::class,
                UserSeeder::class,
                ClubUserSeeder::class,
                ClubAccessSeeder::class,
                ClubApplicationSeeder::class,
                AdminAccessSeeder::class,
            ]);
        }
    }
}
