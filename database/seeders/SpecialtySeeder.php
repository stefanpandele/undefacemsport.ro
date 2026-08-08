<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecialtySeeder extends Seeder
{
    /**
     * What practices do, with the emoji and colour each is presented with, whether
     * somebody with an injury would search for it, and the sports a service of
     * this kind is usually offered for.
     *
     * Sports massage is the one that is not searched for: it sits alongside a real
     * activity — a pilates studio, a gym, a hotel — rather than being one. Listing
     * it under recovery would fill that page with places nobody went there to find.
     *
     * The sport list here is only a default for the seeder to tick onto seeded
     * services. The claim itself lives on the service, because "I treat
     * footballers" is a statement about one practitioner, not about the field.
     *
     * @var array<string, array{string, string, bool, list<string>}>
     */
    private const SPECIALTIES = [
        'Fizioterapie' => ['🤲', '#1D7FB8', true, ['fotbal', 'baschet', 'handbal', 'atletism']],
        'Kinetoterapie' => ['🦵', '#0C7A4E', true, ['fotbal', 'gimnastica', 'atletism']],
        'Medicină sportivă' => ['🩺', '#C0392B', true, ['fotbal', 'baschet', 'atletism', 'inot']],
        'Nutriție sportivă' => ['🥗', '#15B877', true, ['atletism', 'inot', 'ciclism']],
        'Masaj sportiv' => ['💆', '#A25DD1', false, ['fotbal', 'atletism', 'ciclism']],
        'Recuperare după accidentare' => ['🩹', '#FF5A2C', true, ['fotbal', 'baschet', 'handbal']],
        'Psihologie sportivă' => ['🧠', '#5D4DB0', true, ['tenis', 'gimnastica', 'inot']],
        'Podologie' => ['🦶', '#8B5E3C', true, ['atletism', 'fotbal']],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::SPECIALTIES as $name => [$icon, $color, $isMedical]) {
            Specialty::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'color' => $color, 'is_medical' => $isMedical, 'sort_order' => $order++],
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
                Str::slug($name) => $definition[3],
            ])
            ->all();
    }
}
