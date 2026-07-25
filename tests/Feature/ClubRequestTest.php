<?php

use App\Http\Requests\StoreClubRequest;
use App\Http\Requests\UpdateClubRequest;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('store club request rejects a duplicate slug', function () {
    Club::factory()->create(['slug' => 'clubul-meu']);
    Route::post('/__test/clubs', fn (StoreClubRequest $request) => $request->validated())->middleware('web');

    $this->actingAs(User::factory()->create())
        ->postJson('/__test/clubs', ['name' => 'Test', 'slug' => 'clubul-meu'])
        ->assertJsonValidationErrors('slug');
});

test('store club request rejects a duplicate fiscal code', function () {
    Club::factory()->create(['fiscal_code' => 'RO12345678']);
    Route::post('/__test/clubs', fn (StoreClubRequest $request) => $request->validated())->middleware('web');

    $this->actingAs(User::factory()->create())
        ->postJson('/__test/clubs', [
            'name' => 'Test',
            'slug' => 'alt-club',
            'fiscal_code' => 'RO12345678',
        ])
        ->assertJsonValidationErrors('fiscal_code');
});

test('store club request passes with valid data', function () {
    Route::post('/__test/clubs', fn (StoreClubRequest $request) => $request->validated())->middleware('web');

    $this->actingAs(User::factory()->create())
        ->postJson('/__test/clubs', ['name' => 'Clubul Meu', 'slug' => 'clubul-meu'])
        ->assertOk();
});

test('store club request forbids a guest', function () {
    Route::post('/__test/clubs', fn (StoreClubRequest $request) => $request->validated())->middleware('web');

    $this->postJson('/__test/clubs', ['name' => 'X', 'slug' => 'x'])
        ->assertForbidden();
});

test('update club request lets the owner keep the club own slug', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create(['slug' => 'clubul-meu']);
    $club->owner()->associate($owner)->save();

    Route::put('/__test/clubs/{club}', fn (UpdateClubRequest $request, Club $club) => $request->validated())->middleware('web');

    $this->actingAs($owner)
        ->putJson("/__test/clubs/{$club->id}", ['slug' => 'clubul-meu'])
        ->assertOk();
});

test('update club request forbids a non-owner', function () {
    $club = Club::factory()->create();
    Route::put('/__test/clubs/{club}', fn (UpdateClubRequest $request, Club $club) => $request->validated())->middleware('web');

    $this->actingAs(User::factory()->create())
        ->putJson("/__test/clubs/{$club->id}", ['slug' => 'ceva-nou'])
        ->assertForbidden();
});
