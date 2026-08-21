<?php

namespace Database\Seeders;

use App\Models\Sport;
use App\Models\Surface;
use Illuminate\Database\Seeder;

class SurfaceSeeder extends Seeder
{
    /**
     * What a court can be played on, and by which sports.
     *
     * Deliberately one term per physical thing rather than one per sport's
     * vocabulary: a tennis player says "iarbă" and a footballer says "gazon",
     * but it is the same grass, and two rows for it would split every search in
     * half. Sports absent from every list — swimming, chess, boxing — are the
     * point of the map: for them the question is never asked.
     *
     * @var array<string, list<string>>
     */
    public const SURFACES = [
        'Zgură' => ['Tenis'],
        'Iarbă' => ['Tenis', 'Fotbal', 'Rugby'],
        'Hard' => ['Tenis'],
        'Gazon sintetic' => ['Fotbal', 'Rugby', 'Padel'],
        'Parchet' => ['Baschet', 'Handbal', 'Volei', 'Badminton', 'Squash'],
        'Tartan' => ['Atletism'],
        'Beton' => ['Baschet', 'Fotbal'],
    ];

    /**
     * Idempotent by name, and the links are synced rather than attached, so a
     * correction to the map above lands on a re-run instead of accumulating.
     */
    public function run(): void
    {
        $sports = Sport::query()->pluck('id', 'name');

        foreach (array_keys(self::SURFACES) as $order => $name) {
            $surface = Surface::updateOrCreate(['name' => $name], ['sort_order' => $order]);

            $surface->sports()->sync(array_values(array_filter(array_map(
                fn (string $sport): ?int => $sports->get($sport),
                self::SURFACES[$name],
            ))));
        }
    }
}
