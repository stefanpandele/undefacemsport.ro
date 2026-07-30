<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationType;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Sport;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * How many sports the landing page shows before handing over to the full
     * index. Seven, because the eighth cell of the row is the "see all" card —
     * the way out belongs in the grid, not tucked away under it.
     */
    private const POPULAR = 7;

    public function __invoke(): Response
    {
        $sports = Sport::withReach();

        return Inertia::render('Welcome', [
            'popularSports' => array_slice($sports, 0, self::POPULAR),
            // Only sports someone actually teaches: the hero search must not
            // offer a choice that leads to an empty page.
            'sports' => array_map(
                fn (array $sport): array => ['value' => $sport['key'], 'label' => $sport['label']],
                $sports,
            ),
            'cities' => $this->cities(),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Cities with a location, each with a centre point so a visitor who shares
     * their position can be dropped straight into the nearest one.
     *
     * @return list<array{name: string, lat: float|null, lng: float|null}>
     */
    private function cities(): array
    {
        return array_values(DB::table('locations')
            ->whereNotNull('city')
            ->groupBy('city')
            ->select(['city'])
            ->selectRaw('avg(latitude) as lat')
            ->selectRaw('avg(longitude) as lng')
            ->orderBy('city')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->city,
                'lat' => $row->lat === null ? null : (float) $row->lat,
                'lng' => $row->lng === null ? null : (float) $row->lng,
            ])
            ->all());
    }

    /**
     * The headline numbers. Counted, not claimed — a landing page that inflates
     * them is the first thing a club notices is false.
     *
     * @return array{locations: int, clubs: int, cities: int, sports: int}
     */
    private function stats(): array
    {
        return [
            'locations' => Location::query()->count(),
            // Clubs only: a venue renting out a hall is not a club, and the
            // headline number must not quietly claim otherwise.
            'clubs' => Organization::query()
                ->where('type', OrganizationType::Club)
                ->whereHas('organizationLocations')
                ->count(),
            'cities' => Location::query()->whereNotNull('city')->distinct()->count('city'),
            'sports' => count(Sport::withReach()),
        ];
    }
}
