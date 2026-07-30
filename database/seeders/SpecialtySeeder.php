<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecialtySeeder extends Seeder
{
    /**
     * What practices do, with the emoji and colour each is presented with, and the
     * sports a service of this kind is usually offered for.
     *
     * The sport list here is only a default for the seeder to tick onto seeded
     * services. The claim itself lives on the service, because "I treat
     * footballers" is a statement about one practitioner, not about the field.
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
        $order = 0;

        foreach (self::SPECIALTIES as $name => [$icon, $color]) {
            Specialty::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'color' => $color, 'sort_order' => $order++],
            );
        }
    }

    /**
     * The sports a service of each kind is usually offered for, keyed by specialty
     * slug — the defaults PracticeSeeder ticks onto the services it creates.
     *
     * A default for seeding only. The claim itself lives on the service, because
     * "I treat footballers" is a statement about one practitioner rather than a
     * fact about the field.
     *
     * @return array<string, list<string>>
     */
    public static function sportsBySpecialty(): array
    {
        return collect(self::SPECIALTIES)
            ->mapWithKeys(fn (array $definition, string $name): array => [
                Str::slug($name) => $definition[2],
            ])
            ->all();
    }
}
