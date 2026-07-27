<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Common amenities a sports location can offer, with the emoji each one is
     * shown with on the public location page.
     *
     * @var array<string, string>
     */
    private const FACILITIES = [
        'Parcare' => '🅿️',
        'Vestiare' => '🚿',
        'Dușuri' => '🚻',
        'Acces persoane cu dizabilități' => '♿',
        'Bar / Bufet' => '🥤',
        'Tribună' => '🪑',
        'Wi-Fi' => '📶',
        'Încălzire' => '🌡️',
        'Aer condiționat' => '❄️',
        'Iluminat nocturn' => '💡',
        'Cabinet medical' => '🩺',
        'Supraveghere video' => '📹',
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::FACILITIES as $name => $icon) {
            Facility::updateOrCreate(['name' => $name], ['icon' => $icon, 'sort_order' => $order++]);
        }
    }
}
