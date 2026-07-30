<?php

use Illuminate\Support\Facades\File;

test('every number in the platform description still has a live source', function () {
    // The document states counts, limits and inventories. None of them are typed
    // into the template — each is filled from the code that makes it true. This
    // fails when a source moves, so a rename breaks the build instead of quietly
    // publishing a wrong number.
    $this->artisan('docs:build', ['--check' => true])->assertSuccessful();
});

test('every rail link points at a section that exists', function () {
    // The scroll marker bails out entirely if one link has no section, and it
    // does so without a word. A renamed section would silently cost the whole
    // feature rather than one entry.
    $template = File::get(base_path('resources/docs/platform.html'));

    preg_match_all('/<a href="#([a-z-]+)"/', $template, $links);
    preg_match_all('/<section id="([a-z-]+)"/', $template, $sections);

    expect($links[1])->not->toBeEmpty()
        ->and(array_diff($links[1], $sections[1]))->toBeEmpty()
        ->and(array_diff($sections[1], $links[1]))->toBeEmpty();
});

test('the template states no bare counts of its own', function () {
    // A number typed straight into the prose is a number nobody will update. The
    // exceptions are prices and ranges that are part of a sentence rather than a
    // count, so this only guards the table cells the generator owns.
    $template = File::get(base_path('resources/docs/platform.html'));

    preg_match_all('/<td class="num">([^<]+)<\/td>/', $template, $matches);

    $hardcoded = array_values(array_filter(
        $matches[1],
        fn (string $cell): bool => preg_match('/^\s*\d/', $cell) === 1,
    ));

    expect($hardcoded)->toBeEmpty(
        'These cells should be placeholders, not typed numbers: '.implode(', ', $hardcoded),
    );
});
