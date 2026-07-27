<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * NOTE: deliberately does NOT use WithoutModelEvents. Location generates
     * its (non-nullable) slug in a `creating` hook, so suppressing model events
     * makes every seeded location fail the NOT NULL constraint.
     */
    public function run(): void
    {
        if (app()->environment() !== 'production') {
            $this->call([
                AdminPasswordSeeder::class,
                SportSeeder::class,
                AgeGroupSeeder::class,
                FacilitySeeder::class,
                CountySeeder::class,
                LocalitySeeder::class,
                LocationSeeder::class,
                ClubSeeder::class,
                UserSeeder::class,
                ClubUserSeeder::class,
                // Must run before ClubProfileSeeder, so the known demo clubs
                // exist in time to be given a profile like every other club.
                ClubAccessSeeder::class,
                ClubProfileSeeder::class,
                ClubApplicationSeeder::class,
                AdminAccessSeeder::class,
            ]);
        }
    }
}
