<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    /**
     * How far along a group is, in the order a visitor thinks about it: someone
     * who has never done the sport reads the list from the top.
     *
     * @var list<string>
     */
    private const LEVELS = [
        'Inițiere',
        'Grupă de copii',
        'Amatori',
        'Avansați',
        'Performanță',
    ];

    public function run(): void
    {
        foreach (self::LEVELS as $order => $name) {
            Level::updateOrCreate(['name' => $name], ['sort_order' => $order]);
        }
    }
}
