<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Sport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SportController extends Controller
{
    /**
     * Every sport taught anywhere, as a way in for a visitor who knows what
     * they want to play but not where. Picking one leads to the explore page,
     * which then asks only for the city.
     *
     * Narrowing by county comes first in the form, because "what can I play
     * near me" is the question people actually arrive with — the counts then
     * describe that county rather than the whole country.
     */
    public function index(Request $request): Response
    {
        $counties = $this->counties();
        $county = $request->string('judet')->trim()->toString();
        $county = in_array($county, $counties, true) ? $county : null;

        return Inertia::render('public/sports/Index', [
            'sports' => Sport::withReach($county),
            'counties' => $counties,
            'filters' => ['county' => $county],
        ]);
    }

    /**
     * Counties that actually hold a location. Offering the other thirty-odd
     * would be offering dead ends.
     *
     * @return list<string>
     */
    private function counties(): array
    {
        return array_values(Location::query()
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->all());
    }
}
