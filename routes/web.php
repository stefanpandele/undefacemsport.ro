<?php

use App\Http\Controllers\AnafLookupController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OrganizationApplicationController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\SportController;
use App\Http\Middleware\RedirectToHomeArea;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('sporturi', [SportController::class, 'index'])->name('sports.index');
// Real paths rather than query strings: "baschet cluj" is what people search,
// and /explorare?sport=…&oras=… is not something a search engine indexes.
Route::get('sporturi/{slug}/{city?}', [SportController::class, 'show'])->name('sports.show');
Route::get('preturi', PricingController::class)->name('pricing');
Route::get('explorare', [ExploreController::class, 'index'])->name('explore');
Route::get('locatii/{slug}', [LocationController::class, 'show'])->name('locations.show');
Route::get('cluburi/{slug}', [ClubController::class, 'show'])->name('clubs.show');
// A physiotherapist is not a club, and somebody looking for one is not looking
// for training — so the two never share a URL.
Route::get('specialisti/{slug}', [PracticeController::class, 'show'])->name('practices.show');

Route::get('club-application', [OrganizationApplicationController::class, 'create'])->name('club-application.create');
Route::post('club-application', [OrganizationApplicationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('club-application.store');

Route::get('company-lookup', AnafLookupController::class)
    ->middleware('throttle:20,1')
    ->name('anaf.lookup');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard')
        ->middleware(RedirectToHomeArea::class.':user');
});

require __DIR__.'/settings.php';
