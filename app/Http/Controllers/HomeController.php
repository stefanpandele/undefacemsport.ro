<?php

namespace App\Http\Controllers;

use App\Models\Sport;
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
        return Inertia::render('Welcome', [
            'popularSports' => array_slice(Sport::withReach(), 0, self::POPULAR),
        ]);
    }
}
