<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    /**
     * Show a single sports location with the clubs that train there.
     *
     * NOTE: Location/Club/Coach models do not exist yet. This returns
     * placeholder data with the final prop shape so the page is ready to be
     * wired to real data later — only this method's body changes.
     */
    public function show(string $slug): Response
    {
        return Inertia::render('locations/Show', [
            'location' => $this->placeholderLocation($slug),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderLocation(string $slug): array
    {
        return [
            'slug' => $slug,
            'name' => 'Bazinul Olimpic Brașov',
            'address' => 'Str. Lungă 12, Brașov',
            'distance' => '3.4 km de mine',
            'gallery' => ['g1', 'g2', 'g3', 'g4', 'g5'],
            'facilities' => [
                ['icon' => '🚿', 'label' => 'Vestiar'],
                ['icon' => '🅿️', 'label' => 'Parcare'],
                ['icon' => '🚻', 'label' => 'Dușuri'],
                ['icon' => '💡', 'label' => 'Nocturnă'],
                ['icon' => '🧴', 'label' => 'Uscător de păr'],
                ['icon' => '🏊', 'label' => '6 culoare'],
                ['icon' => '🌡️', 'label' => 'Apă încălzită'],
                ['icon' => '♿', 'label' => 'Acces persoane cu dizabilități'],
            ],
            'sports' => [
                ['key' => 'inot', 'label' => 'Înot', 'icon' => '🏊', 'clubCount' => 2],
                ['key' => 'polo', 'label' => 'Polo', 'icon' => '🤽', 'clubCount' => 1],
                ['key' => 'aqua-gym', 'label' => 'Aqua gym', 'icon' => '💧', 'clubCount' => 0],
            ],
            'clubs' => [
                [
                    'slug' => 'club-aqua-junior',
                    'sport' => 'inot',
                    'name' => 'Club Aqua Junior',
                    'representative' => 'Andrei Popescu, antrenor principal',
                    'about' => 'Înot pentru copii, grupe mici, focus pe siguranță în apă și tehnică de bază.',
                    'photos' => 7,
                    'trustChips' => [
                        ['label' => '🏅 Licențiat FR Natație', 'solo' => false],
                        ['label' => '📅 5 ani activi', 'solo' => false],
                        ['label' => '👥 120+ elevi', 'solo' => false],
                        ['label' => '🎯 Antrenament 1:1 disponibil', 'solo' => true],
                    ],
                    'ages' => ['0–2 ani', '3–7 ani', 'Începători'],
                    'coaches' => [
                        ['key' => 'andrei', 'name' => 'Andrei Popescu', 'role' => 'Antrenor principal', 'solo' => true, 'gradient' => 'linear-gradient(135deg,#0C7A4E,#15B877)', 'bio' => 'Licențiat FR Natație, 5 ani experiență cu grupe de copii. Disponibil și pentru ședințe 1:1.'],
                        ['key' => 'maria', 'name' => 'Maria Dumitru', 'role' => 'Antrenoare', 'solo' => false, 'gradient' => 'linear-gradient(135deg,#5d2c8a,#a25dd1)', 'bio' => 'Specializată în adaptare la apă pentru cei mici, 0–2 ani, în grupe mici.'],
                    ],
                    'schedule' => [
                        ['day' => 'LUN', 'slots' => [['time' => '10:00–10:45', 'group' => '0–2 ani', 'coach' => 'maria'], ['time' => '17:00–18:00', 'group' => '3–7 ani', 'coach' => 'andrei']]],
                        ['day' => 'MAR', 'slots' => []],
                        ['day' => 'MIE', 'slots' => [['time' => '10:00–10:45', 'group' => '0–2 ani', 'coach' => 'maria'], ['time' => '17:00–18:00', 'group' => '3–7 ani', 'coach' => 'andrei']]],
                        ['day' => 'JOI', 'slots' => []],
                        ['day' => 'VIN', 'slots' => [['time' => '17:00–18:00', 'group' => '3–7 ani', 'coach' => 'andrei']]],
                        ['day' => 'SÂM', 'slots' => []],
                        ['day' => 'DUM', 'slots' => []],
                    ],
                    'contactName' => 'Andrei Popescu',
                    'contactPhone' => '07xx xxx xxx',
                ],
                [
                    'slug' => 'club-polo-brasov',
                    'sport' => 'polo',
                    'name' => 'Club Polo Brașov',
                    'representative' => 'Mihai Ionescu, antrenor',
                    'about' => 'Polo pentru copii și juniori, pregătire pentru competiții regionale.',
                    'photos' => 3,
                    'trustChips' => [
                        ['label' => '🏅 Fost jucător național', 'solo' => false],
                        ['label' => '📅 3 ani activi', 'solo' => false],
                        ['label' => '🎯 Antrenament 1:1 disponibil', 'solo' => true],
                    ],
                    'ages' => ['10–14 ani', 'Avansați'],
                    'coaches' => [
                        ['key' => 'mihai', 'name' => 'Mihai Ionescu', 'role' => 'Antrenor', 'solo' => true, 'gradient' => 'linear-gradient(135deg,#0b3a5e,#1d7fb8)', 'bio' => 'Pregătire pentru competiții regionale, juniori 10–14 ani. Disponibil și pentru ședințe 1:1.'],
                    ],
                    'schedule' => [
                        ['day' => 'LUN', 'slots' => []],
                        ['day' => 'MAR', 'slots' => [['time' => '18:00–19:30', 'group' => '10–14 ani', 'coach' => 'mihai']]],
                        ['day' => 'MIE', 'slots' => []],
                        ['day' => 'JOI', 'slots' => [['time' => '18:00–19:30', 'group' => '10–14 ani', 'coach' => 'mihai']]],
                        ['day' => 'VIN', 'slots' => []],
                        ['day' => 'SÂM', 'slots' => []],
                        ['day' => 'DUM', 'slots' => []],
                    ],
                    'contactName' => 'Mihai Ionescu',
                    'contactPhone' => '07xx xxx xxx',
                ],
                [
                    'slug' => 'aqua-masters-brasov',
                    'sport' => 'inot',
                    'name' => 'Aqua Masters Brașov',
                    'representative' => 'Elena Radu, coordonator',
                    'about' => 'Înot pentru adulți, toate nivelurile — de la recuperare la performanță masters.',
                    'photos' => 8,
                    'trustChips' => [
                        ['label' => '🏅 Antrenori certificați', 'solo' => false],
                        ['label' => '📅 8 ani activi', 'solo' => false],
                        ['label' => '👥 200+ elevi', 'solo' => false],
                    ],
                    'ages' => ['Adulți', 'Toate nivelurile'],
                    'coaches' => [
                        ['key' => 'elena', 'name' => 'Elena Radu', 'role' => 'Coordonator', 'solo' => false, 'gradient' => 'linear-gradient(135deg,#7a3410,#d2691e)', 'bio' => 'Programe pentru adulți, de la recuperare la performanță masters.'],
                    ],
                    'schedule' => [
                        ['day' => 'LUN', 'slots' => [['time' => '20:00–21:00', 'group' => 'Adulți', 'coach' => 'elena']]],
                        ['day' => 'MAR', 'slots' => []],
                        ['day' => 'MIE', 'slots' => [['time' => '20:00–21:00', 'group' => 'Adulți', 'coach' => 'elena']]],
                        ['day' => 'JOI', 'slots' => []],
                        ['day' => 'VIN', 'slots' => [['time' => '20:00–21:00', 'group' => 'Adulți', 'coach' => 'elena']]],
                        ['day' => 'SÂM', 'slots' => []],
                        ['day' => 'DUM', 'slots' => [['time' => '09:00–10:00', 'group' => 'Adulți', 'coach' => 'elena']]],
                    ],
                    'contactName' => 'Elena Radu',
                    'contactPhone' => '07xx xxx xxx',
                ],
            ],
        ];
    }
}
