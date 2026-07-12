<?php

use App\Http\Controllers\ClubApplicationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('inscriere-club', [ClubApplicationController::class, 'create'])->name('club-application.create');
Route::post('inscriere-club', [ClubApplicationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('club-application.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
