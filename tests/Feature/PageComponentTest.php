<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Inertia resolves a page by name in the browser, so a name with no file behind
 * it is not something the server can notice: the response is 200, the payload is
 * right, and the visitor gets `Page not found: …` in the console over an empty
 * screen. Renaming ClubApplicationController to OrganizationApplicationController
 * without renaming the directory under `resources/js/pages` did exactly that to
 * the sign-up form, with the suite green.
 *
 * Reading the names out of the source rather than listing them here means a new
 * page is covered the moment it is written.
 *
 * @return list<string>
 */
function renderedPageNames(): array
{
    $names = [];

    foreach (['app', 'routes'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = $file->getContents();

            preg_match_all("/Inertia::render\(\s*'([^']+)'/", $source, $rendered);
            preg_match_all("/Route::inertia\(\s*'[^']*'\s*,\s*'([^']+)'/", $source, $routed);

            $names = array_merge($names, $rendered[1], $routed[1]);
        }
    }

    return array_values(array_unique($names));
}

test('every page name the application renders has a component behind it', function () {
    $names = renderedPageNames();

    // A regex that stops matching would otherwise turn this into a test that
    // passes by checking nothing.
    expect(count($names))->toBeGreaterThanOrEqual(15);

    $missing = array_values(array_filter(
        $names,
        fn (string $name): bool => ! File::exists(resource_path("js/pages/{$name}.vue")),
    ));

    expect($missing)->toBe([], 'No Vue component for: '.implode(', ', $missing));
});

/**
 * Wayfinder types the links Vue makes to our own controllers, but the Filament
 * panels are outside it, so those hrefs are typed by hand. Nothing then notices
 * when the panel moves: the header pointed at /club/login for a while after the
 * panel had become /cont, which is a dead link on a button labelled Login.
 */
test('every hardcoded link in a Vue component points at a real route', function () {
    $paths = [];

    foreach (File::allFiles(resource_path('js')) as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        preg_match_all(
            '/href[=:] ?["\'](\/[a-zA-Z0-9\/_-]*)["\']/',
            $file->getContents(),
            $matches,
        );

        foreach ($matches[1] as $path) {
            $paths[$path][] = $file->getRelativePathname();
        }
    }

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path => $files) {
        try {
            Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (NotFoundHttpException) {
            $this->fail("{$path} is hardcoded in ".implode(', ', $files).' but no route answers it.');
        }
    }
});
