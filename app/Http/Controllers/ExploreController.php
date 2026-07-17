<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ExploreController extends Controller
{
    /**
     * Browse locations and sports for a city.
     *
     * NOTE: placeholder data with the final prop shape until Location/Sport
     * models are wired in — only this method changes later.
     */
    public function index(): Response
    {
        return Inertia::render('explore/Index', [
            'city' => 'Cluj-Napoca',
            'locations' => [
                ['slug' => 'sala-polivalenta-cluj', 'name' => 'Sala Polivalentă Cluj', 'city' => 'CLUJ-NAPOCA', 'distance' => '1.2 km', 'photo' => 'g3', 'live' => true, 'clubCount' => 8, 'facilityCount' => 6, 'sports' => [['key' => 'baschet', 'label' => 'Baschet'], ['key' => 'volei', 'label' => 'Volei'], ['key' => 'handbal', 'label' => 'Handbal']]],
                ['slug' => 'baza-sportiva-gheorgheni', 'name' => 'Baza Sportivă Gheorgheni', 'city' => 'CLUJ-NAPOCA', 'distance' => '2.4 km', 'photo' => 'g1', 'live' => false, 'clubCount' => 3, 'facilityCount' => 4, 'sports' => [['key' => 'inot', 'label' => 'Înot'], ['key' => 'aqua-gym', 'label' => 'Aqua gym']]],
                ['slug' => 'arenele-tenis-grigorescu', 'name' => 'Arenele Tenis Grigorescu', 'city' => 'CLUJ-NAPOCA', 'distance' => '3.1 km', 'photo' => 'g4', 'live' => false, 'clubCount' => 5, 'facilityCount' => 5, 'sports' => [['key' => 'tenis', 'label' => 'Tenis'], ['key' => 'padel', 'label' => 'Padel']]],
                ['slug' => 'stadionul-municipal', 'name' => 'Stadionul Municipal', 'city' => 'CLUJ-NAPOCA', 'distance' => '3.7 km', 'photo' => 'g2', 'live' => false, 'clubCount' => 6, 'facilityCount' => 7, 'sports' => [['key' => 'fotbal', 'label' => 'Fotbal']]],
                ['slug' => 'sala-horea', 'name' => 'Sala Horea', 'city' => 'CLUJ-NAPOCA', 'distance' => '4.5 km', 'photo' => 'g3', 'live' => false, 'clubCount' => 4, 'facilityCount' => 3, 'sports' => [['key' => 'fitness', 'label' => 'Fitness'], ['key' => 'baschet', 'label' => 'Baschet']]],
                ['slug' => 'bazinul-universitar', 'name' => 'Bazinul Universitar', 'city' => 'CLUJ-NAPOCA', 'distance' => '5.0 km', 'photo' => 'g1', 'live' => false, 'clubCount' => 7, 'facilityCount' => 5, 'sports' => [['key' => 'inot', 'label' => 'Înot'], ['key' => 'polo', 'label' => 'Polo']]],
            ],
            'sports' => [
                ['key' => 'baschet', 'label' => 'Baschet', 'icon' => '🏀', 'head' => 'g6', 'locationCount' => 21, 'clubCount' => 54, 'ages' => ['Copii', 'Adulți']],
                ['key' => 'inot', 'label' => 'Înot', 'icon' => '🏊', 'head' => 'g1', 'locationCount' => 14, 'clubCount' => 38, 'ages' => ['Bebeluși', 'Copii', 'Adulți']],
                ['key' => 'tenis', 'label' => 'Tenis', 'icon' => '🎾', 'head' => 'g4', 'locationCount' => 11, 'clubCount' => 27, 'ages' => ['Copii', 'Adulți']],
                ['key' => 'volei', 'label' => 'Volei', 'icon' => '🏐', 'head' => 'g3', 'locationCount' => 9, 'clubCount' => 19, 'ages' => ['Adulți']],
                ['key' => 'fotbal', 'label' => 'Fotbal', 'icon' => '⚽', 'head' => 'g6', 'locationCount' => 17, 'clubCount' => 41, 'ages' => ['Copii', 'Adulți']],
                ['key' => 'tenis-de-masa', 'label' => 'Tenis de masă', 'icon' => '🏓', 'head' => 'g1', 'locationCount' => 8, 'clubCount' => 16, 'ages' => ['Copii', 'Adulți']],
            ],
        ]);
    }
}
