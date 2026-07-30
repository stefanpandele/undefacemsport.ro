<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Enums\PersonProfession;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\Person;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PracticeSeeder extends Seeder
{
    /**
     * What each practice sells, by specialty slug: name, minutes, price.
     *
     * @var array<string, list<array{string, int, int|null}>>
     */
    private const SERVICES = [
        'fizioterapie' => [
            ['Consultație inițială', 50, 200],
            ['Ședință de fizioterapie', 50, 180],
            ['Pachet 5 ședințe', 50, 800],
        ],
        'kinetoterapie' => [
            ['Evaluare funcțională', 60, 250],
            ['Ședință de kinetoterapie', 50, 170],
        ],
        'medicina-sportiva' => [
            ['Consultație medicină sportivă', 40, 300],
            ['Aviz medical sportiv', 30, 150],
        ],
        'nutritie-sportiva' => [
            ['Consultație nutriție', 60, 250],
            ['Plan alimentar personalizat', 45, 350],
            ['Monitorizare lunară', 30, 120],
        ],
        'masaj-sportiv' => [
            ['Masaj sportiv', 50, 150],
            ['Masaj de recuperare', 60, 180],
        ],
        'recuperare-dupa-accidentare' => [
            ['Evaluare post-accidentare', 60, 280],
            ['Program de recuperare', 50, 200],
        ],
        'psihologie-sportiva' => [
            ['Ședință de psihologie sportivă', 50, 220],
        ],
        'podologie' => [
            // Deliberately unpriced: an unknown price is a real state, and the
            // public page has to be seen saying so rather than promising nothing.
            ['Consultație podologie', 40, null],
        ],
    ];

    /** @var list<string> */
    private const FIRST_NAMES = ['Ioana', 'Andrei', 'Elena', 'Cristian', 'Maria', 'Radu', 'Ana', 'Vlad'];

    /** @var list<string> */
    private const LAST_NAMES = ['Marinescu', 'Popescu', 'Ionescu', 'Georgescu', 'Munteanu', 'Barbu'];

    /**
     * The profession each specialty is practised by.
     *
     * @var array<string, string>
     */
    private const PROFESSIONS = [
        'fizioterapie' => 'physiotherapist',
        'kinetoterapie' => 'physiotherapist',
        'medicina-sportiva' => 'doctor',
        'nutritie-sportiva' => 'nutritionist',
        'masaj-sportiv' => 'physiotherapist',
        'recuperare-dupa-accidentare' => 'physiotherapist',
        'psihologie-sportiva' => 'doctor',
        'podologie' => 'doctor',
    ];

    /**
     * Deliberately ordered rather than random: which practice sells what has to
     * come out the same on every run, or re-seeding piles a second set of services
     * on top of the first.
     */
    public function run(): void
    {
        $specialties = Specialty::query()->orderBy('sort_order')->get();
        $locations = Location::query()->orderBy('id')->get();

        if ($specialties->isEmpty() || $locations->isEmpty()) {
            return;
        }

        // The practices themselves come from OrganizationSeeder, so the user
        // seeder has already given them members. This only gives them an offer.
        Organization::query()
            ->where('type', OrganizationType::Practice)
            ->doesntHave('services')
            ->orderBy('id')
            ->get()
            ->each(function (Organization $practice, int $index) use ($specialties, $locations): void {
                // A lone practitioner every third one: the PFA case is the common
                // one in reality, and it must look right too.
                $solo = $index % 3 === 0;
                $offered = $specialties
                    ->slice($index % $specialties->count())
                    ->take($solo ? 1 : 2)
                    ->values();

                if ($offered->isEmpty()) {
                    $offered = $specialties->take(1)->values();
                }

                $people = $this->addPeople($practice, $offered, $solo);
                $this->addServices($practice, $offered, $people);

                OrganizationLocation::firstOrCreate([
                    'organization_id' => $practice->getKey(),
                    'location_id' => $locations[$index % $locations->count()]->getKey(),
                ]);

                $practice->update([
                    'description' => $solo
                        ? 'Cabinet individual, cu programări la '.$offered->first()->name.'.'
                        : 'Clinică cu echipă multidisciplinară: '.$offered->pluck('name')->implode(' și ').'.',
                ]);
            });
    }

    /**
     * @param  Collection<int, Specialty>  $offered
     * @return Collection<int, Person>
     */
    private function addPeople(Organization $practice, Collection $offered, bool $solo): Collection
    {
        return $offered
            ->take($solo ? 1 : 3)
            ->values()
            ->map(function (Specialty $specialty, int $position) use ($practice): Person {
                $profession = PersonProfession::from(self::PROFESSIONS[$specialty->slug] ?? 'physiotherapist');

                return $practice->people()->create([
                    'name' => self::FIRST_NAMES[($practice->getKey() + $position) % count(self::FIRST_NAMES)]
                        .' '.self::LAST_NAMES[($practice->getKey() + $position) % count(self::LAST_NAMES)],
                    'profession' => $profession,
                    // The job title follows the profession, so the card never reads
                    // "Antrenor principal" above a nutritionist.
                    'role' => $profession->label(),
                    'bio' => 'Specializat în '.mb_strtolower($specialty->name).'.',
                    'is_primary' => $position === 0,
                    'sort_order' => $position,
                ]);
            });
    }

    /**
     * @param  Collection<int, Specialty>  $offered
     * @param  Collection<int, Person>  $people
     */
    private function addServices(Organization $practice, Collection $offered, Collection $people): void
    {
        $sports = Sport::query()->pluck('id', 'slug');
        $defaults = SpecialtySeeder::sportsBySpecialty();
        $order = 0;

        foreach ($offered as $index => $specialty) {
            foreach (self::SERVICES[$specialty->slug] ?? [] as [$name, $minutes, $price]) {
                $service = Service::firstOrCreate(
                    [
                        'organization_id' => $practice->getKey(),
                        'name' => $name,
                    ],
                    [
                        'specialty_id' => $specialty->getKey(),
                        // Left open on some, so the "oricine din echipă" case shows.
                        'person_id' => $order % 3 === 0 ? null : $people->get($index)?->getKey(),
                        'duration_minutes' => $minutes,
                        'price' => $price,
                        'sort_order' => $order++,
                    ],
                );

                // The practitioner's own claim about which athletes they treat.
                // Seeded from a per-specialty default, but stored on the service —
                // in the panel it is a tick list the practitioner fills in.
                $service->sports()->syncWithoutDetaching(
                    collect($defaults[$specialty->slug] ?? [])
                        ->map(fn (string $slug): ?int => $sports->get($slug))
                        ->filter()
                        ->all(),
                );
            }
        }
    }
}
