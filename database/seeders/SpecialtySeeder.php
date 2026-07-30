<?php

namespace Database\Seeders;

use App\Models\Specialty;
use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecialtySeeder extends Seeder
{
    /**
     * What practices do, with the emoji and colour each is presented with, and the
     * sports it is commonly sought for.
     *
     * The sport links exist so a page can relate the two worlds — "recuperare
     * după accidentare" next to football — never so a physiotherapist gets counted
     * as a club.
     *
     * @var array<string, array{string, string, list<string>}>
     */
    private const SPECIALTIES = [
        'Fizioterapie' => ['🤲', '#1D7FB8', ['fotbal', 'baschet', 'handbal', 'atletism']],
        'Kinetoterapie' => ['🦵', '#0C7A4E', ['fotbal', 'gimnastica', 'atletism']],
        'Medicină sportivă' => ['🩺', '#C0392B', ['fotbal', 'baschet', 'atletism', 'inot']],
        'Nutriție sportivă' => ['🥗', '#15B877', ['atletism', 'inot', 'ciclism']],
        'Masaj sportiv' => ['💆', '#A25DD1', ['fotbal', 'atletism', 'ciclism']],
        'Recuperare după accidentare' => ['🩹', '#FF5A2C', ['fotbal', 'baschet', 'handbal']],
        'Psihologie sportivă' => ['🧠', '#5D4DB0', ['tenis', 'gimnastica', 'inot']],
        'Podologie' => ['🦶', '#8B5E3C', ['atletism', 'fotbal']],
    ];

    public function run(): void
    {
        $sports = Sport::query()->pluck('id', 'slug');
        $order = 0;

        foreach (self::SPECIALTIES as $name => [$icon, $color, $forSports]) {
            $specialty = Specialty::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'color' => $color, 'sort_order' => $order++],
            );

            $specialty->sports()->syncWithoutDetaching(
                collect($forSports)->map(fn (string $slug): ?int => $sports->get($slug))->filter()->all(),
            );
        }
    }
}
