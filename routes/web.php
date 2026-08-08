<?php

use App\Http\Controllers\AnafLookupController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OrganizationApplicationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\SportController;
use App\Http\Middleware\RedirectToHomeArea;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('sporturi', [SportController::class, 'index'])->name('sports.index');
Route::get('sporturi/{slug}/{city?}', [SportController::class, 'show'])->name('sports.show');
Route::get('preturi', PricingController::class)->name('pricing');
Route::get('explorare', [ExploreController::class, 'index'])->name('explore');
Route::get('locatii/{slug}', [LocationController::class, 'show'])->name('locations.show');

// A preposition rather than a category: the same company can teach, rent out a
// pool and employ a physiotherapist, and no single noun is true of it. What it
// offers decides which tabs the page has; the fragment decides which one opens.
Route::get('la/{slug}', [OrganizationController::class, 'show'])->name('organizations.show');

// The addresses these pages used to live at. Indexed and shared, so they keep
// working — permanently, because nothing is coming back to them.
Route::permanentRedirect('cluburi/{slug}', 'la/{slug}');
Route::permanentRedirect('specialisti/{slug}', 'la/{slug}');

Route::get('organization-application', [OrganizationApplicationController::class, 'create'])
    ->name('organization-application.create');
Route::post('organization-application', [OrganizationApplicationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('organization-application.store');

Route::get('company-lookup', AnafLookupController::class)
    ->middleware('throttle:20,1')
    ->name('anaf.lookup');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard')
        ->middleware(RedirectToHomeArea::class.':user');
});

require __DIR__.'/settings.php';
