<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    /**
     * Show a public club profile with its sports, coaches and per-location schedule.
     *
     * NOTE: Club/Coach/Location models are not wired yet. Returns placeholder
     * data with the final prop shape so only this method changes later.
     */
    public function show(string $slug): Response
    {
        return Inertia::render('clubs/Show', [
            'club' => $this->placeholderClub($slug),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderClub(string $slug): array
    {
        return [
            'slug' => $slug,
            'name' => 'Club Aqua Junior',
            'representative' => 'Andrei Popescu, antrenor principal',
            'about' => 'Școală de sporturi acvatice pentru copii — de la primele lecții de înot până la introducere în polo, în grupe mici, cu accent pe siguranță.',
            'socials' => [
                ['label' => 'ig', 'url' => '#'],
                ['label' => 'fb', 'url' => '#'],
                ['label' => '🌐', 'url' => '#'],
            ],
            'sports' => [
                ['key' => 'inot', 'label' => 'Înot', 'icon' => '🏊', 'locationCount' => 2],
                ['key' => 'polo', 'label' => 'Polo', 'icon' => '🤽', 'locationCount' => 1],
            ],
            'sportDetails' => [
                'inot' => [
                    'title' => '🏊 Despre înot la Aqua Junior',
                    'trustChips' => [
                        ['label' => '🏅 Licențiat FR Natație', 'solo' => false],
                        ['label' => '📅 5 ani activitate', 'solo' => false],
                        ['label' => '👥 120+ elevi', 'solo' => false],
                        ['label' => '🎯 Antrenament 1:1 disponibil', 'solo' => true],
                    ],
                    'ages' => ['0–2 ani', '3–7 ani', 'Începători'],
                    'gallery' => ['g1', 'g2', 'g3', 'g6'],
                ],
                'polo' => [
                    'title' => '🤽 Despre polo la Aqua Junior',
                    'trustChips' => [
                        ['label' => '🏅 Licențiat FR Polo', 'solo' => false],
                        ['label' => '📅 3 ani activitate', 'solo' => false],
                        ['label' => '👥 30+ elevi', 'solo' => false],
                        ['label' => '🎯 Antrenament 1:1 disponibil', 'solo' => true],
                    ],
                    'ages' => ['10–14 ani', 'Avansați'],
                    'gallery' => ['g4', 'g5', 'g7'],
                ],
            ],
            'coaches' => [
                ['key' => 'andrei', 'name' => 'Andrei Popescu', 'role' => 'Antrenor principal', 'sportIcon' => '🏊', 'sportLabel' => 'Înot', 'solo' => true, 'gradient' => 'linear-gradient(135deg,#0C7A4E,#15B877)', 'bio' => 'Licențiat FR Natație, 5 ani experiență cu grupe de copii.'],
                ['key' => 'maria', 'name' => 'Maria Dumitru', 'role' => 'Antrenoare', 'sportIcon' => '🏊', 'sportLabel' => 'Înot', 'solo' => false, 'gradient' => 'linear-gradient(135deg,#5d2c8a,#a25dd1)', 'bio' => 'Specializată în adaptare la apă pentru cei mici, 0–2 ani.'],
                ['key' => 'cristian', 'name' => 'Cristian Vasile', 'role' => 'Antrenor', 'sportIcon' => '🤽', 'sportLabel' => 'Polo', 'solo' => true, 'gradient' => 'linear-gradient(135deg,#0b3a5e,#1d7fb8)', 'bio' => 'Fost jucător național, licențiat FR Polo.'],
            ],
            'locationsBySport' => [
                'inot' => [
                    [
                        'slug' => 'bazinul-olimpic-brasov',
                        'name' => 'Bazinul Olimpic Brașov',
                        'city' => 'BRAȘOV',
                        'schedule' => [
                            ['day' => 'LUN', 'slots' => [['time' => '10:00–10:45', 'group' => '0–2 ani', 'coach' => 'maria'], ['time' => '17:00–18:00', 'group' => '3–7 ani', 'coach' => 'andrei']]],
                            ['day' => 'MAR', 'slots' => []],
                            ['day' => 'MIE', 'slots' => [['time' => '10:00–10:45', 'group' => '0–2 ani', 'coach' => 'maria'], ['time' => '17:00–18:00', 'group' => '3–7 ani', 'coach' => 'andrei']]],
                            ['day' => 'JOI', 'slots' => []],
                            ['day' => 'VIN', 'slots' => [['time' => '17:00–18:00', 'group' => '3–7 ani', 'coach' => 'andrei']]],
                            ['day' => 'SÂM', 'slots' => []],
                            ['day' => 'DUM', 'slots' => []],
                        ],
                    ],
                    [
                        'slug' => 'bazinul-municipal-sacele',
                        'name' => 'Bazinul Municipal Săcele',
                        'city' => 'SĂCELE',
                        'schedule' => [
                            ['day' => 'LUN', 'slots' => []],
                            ['day' => 'MAR', 'slots' => []],
                            ['day' => 'MIE', 'slots' => []],
                            ['day' => 'JOI', 'slots' => []],
                            ['day' => 'VIN', 'slots' => []],
                            ['day' => 'SÂM', 'slots' => [['time' => '10:00–11:30', 'group' => '3–7 ani', 'coach' => 'andrei']]],
                            ['day' => 'DUM', 'slots' => []],
                        ],
                    ],
                ],
                'polo' => [
                    [
                        'slug' => 'bazinul-olimpic-brasov',
                        'name' => 'Bazinul Olimpic Brașov',
                        'city' => 'BRAȘOV',
                        'schedule' => [
                            ['day' => 'LUN', 'slots' => []],
                            ['day' => 'MAR', 'slots' => [['time' => '18:00–19:30', 'group' => '10–14 ani', 'coach' => 'cristian']]],
                            ['day' => 'MIE', 'slots' => []],
                            ['day' => 'JOI', 'slots' => [['time' => '18:00–19:30', 'group' => '10–14 ani', 'coach' => 'cristian']]],
                            ['day' => 'VIN', 'slots' => []],
                            ['day' => 'SÂM', 'slots' => []],
                            ['day' => 'DUM', 'slots' => []],
                        ],
                    ],
                ],
            ],
        ];
    }
}
