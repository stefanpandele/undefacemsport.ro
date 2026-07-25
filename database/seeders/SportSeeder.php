<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SportSeeder extends Seeder
{
    /**
     * Seed the global sport taxonomy that clubs pick from. Idempotent by slug,
     * so it's safe to re-run and to keep as a real reference data set.
     */
    public function run(): void
    {
        $sports = [
            'Fotbal',
            'Baschet',
            'Handbal',
            'Volei',
            'Tenis',
            'Tenis de masă',
            'Înot',
            'Polo',
            'Atletism',
            'Gimnastică',
            'Judo',
            'Karate',
            'Box',
            'Ciclism',
            'Scrimă',
            'Rugby',
            'Badminton',
            'Șah',
            'Dans sportiv',
        ];

        foreach ($sports as $name) {
            Sport::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
