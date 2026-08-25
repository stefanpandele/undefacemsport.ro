<?php

use Filament\Forms\Components\Select;

it('finds an option written with diacritics when the search has none', function () {
    $select = Select::make('sport')->options([
        7 => 'Înot',
        8 => 'Șah',
        9 => 'Fotbal',
    ]);

    expect($select->getSearchResults('inot'))->toBe([7 => 'Înot'])
        ->and($select->getSearchResults('sah'))->toBe([8 => 'Șah']);
});

it('finds an option written without diacritics when the search has them', function () {
    $select = Select::make('city')->options([
        1 => 'Ramnicu Valcea',
        2 => 'Cluj-Napoca',
    ]);

    expect($select->getSearchResults('Râmnicu Vâlcea'))->toBe([1 => 'Ramnicu Valcea']);
});

it('still ignores case and matches inside the label', function () {
    $select = Select::make('sport')->options([7 => 'Înot', 9 => 'Fotbal']);

    expect($select->getSearchResults('NOT'))->toBe([7 => 'Înot'])
        ->and($select->getSearchResults('bal'))->toBe([9 => 'Fotbal']);
});

it('searches inside option groups', function () {
    $select = Select::make('sport')->options([
        'Apă' => [7 => 'Înot', 10 => 'Polo'],
        'Sală' => [9 => 'Fotbal'],
    ]);

    expect($select->getSearchResults('inot'))->toBe(['Apă' => [7 => 'Înot']]);
});

it('returns nothing when no label matches', function () {
    $select = Select::make('sport')->options([7 => 'Înot']);

    expect($select->getSearchResults('tenis'))->toBe([]);
});
