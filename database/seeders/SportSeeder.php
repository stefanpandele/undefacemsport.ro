<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SportSeeder extends Seeder
{
    /**
     * The global sport taxonomy, each with the emoji and the base colour used
     * to present it on the public pages.
     *
     * @var array<string, array{string, string}>
     */
    private const SPORTS = [
        'Fotbal' => ['⚽', '#15B877'],
        'Baschet' => ['🏀', '#E07A2F'],
        'Handbal' => ['🤾', '#2F6FD0'],
        'Volei' => ['🏐', '#E0B32F'],
        'Tenis' => ['🎾', '#A3C614'],
        'Tenis de masă' => ['🏓', '#1D7FB8'],
        'Înot' => ['🏊', '#1D7FB8'],
        'Polo' => ['🤽', '#0C7A4E'],
        'Atletism' => ['🏃', '#D2691E'],
        'Gimnastică' => ['🤸', '#A25DD1'],
        'Judo' => ['🥋', '#5D4DB0'],
        'Karate' => ['🥋', '#8B5E3C'],
        'Box' => ['🥊', '#C0392B'],
        'Ciclism' => ['🚴', '#16A085'],
        'Scrimă' => ['🤺', '#6B7280'],
        'Rugby' => ['🏉', '#7A3410'],
        'Badminton' => ['🏸', '#0EA5A5'],
        'Șah' => ['♟️', '#3A3A3A'],
        'Dans sportiv' => ['💃', '#D6336C'],
        // Rented by the hour far more often than taught, which is exactly why the
        // taxonomy needs them: a padel court and a gym are what a venue sells.
        'Padel' => ['🎾', '#14919B'],
        'Fitness' => ['🏋️', '#8E44AD'],
    ];

    /**
     * Seed the global sport taxonomy that clubs pick from. Idempotent by slug,
     * so it's safe to re-run and to keep as a real reference data set.
     */
    public function run(): void
    {
        foreach (self::SPORTS as $name => [$icon, $color]) {
            Sport::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'color' => $color],
            );
        }
    }
}
