<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClubApplicationRequest;
use App\Models\ClubApplication;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClubApplicationController extends Controller
{
    /**
     * Show the public club application form.
     */
    public function create(): Response
    {
        return Inertia::render('ClubApplication/Create');
    }

    /**
     * Store a new pending club application.
     */
    public function store(StoreClubApplicationRequest $request): RedirectResponse
    {
        ClubApplication::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cererea a fost trimisă. Te contactăm după evaluare.'),
        ]);

        return to_route('club-application.create');
    }
}
