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
                OrganizationSeeder::class,
                UserSeeder::class,
                OrganizationUserSeeder::class,
                // Must run before OrganizationProfileSeeder, so the known demo clubs
                // exist in time to be given a profile like every other club.
                OrganizationAccessSeeder::class,
                OrganizationProfileSeeder::class,
                OrganizationApplicationSeeder::class,
                AdminAccessSeeder::class,
                // Last: needs clubs, locations and admin users to already exist,
                // so it can file proposals from real clubs and mark some of them
                // reviewed by a real admin.
                LocationCorrectionSeeder::class,
            ]);
        }
    }
}
