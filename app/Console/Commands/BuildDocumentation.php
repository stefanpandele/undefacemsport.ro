<?php

namespace App\Console\Commands;

use App\Enums\LocationWay;
use App\Enums\OrganizationType;
use App\Enums\PersonProfession;
use App\Enums\Plan;
use Database\Seeders\AgeGroupSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\OrganizationProfileSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\PracticeSeeder;
use Database\Seeders\SpaceSeeder;
use Database\Seeders\SpecialtySeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SurfaceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Response as InertiaResponse;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

/**
 * Builds the public description of the platform from the code that runs it.
 *
 * The document has two kinds of content and they rot at very different speeds.
 * Counts, limits and inventories go stale in silence, so none of them are typed
 * into the template — every one is a placeholder filled from a live source here.
 * The judgement — why a type is an identity and not a permission, why a price of
 * zero differs from no price at all — stays hand-written, because it changes only
 * when somebody decides it should, and cannot be derived from anything.
 *
 * A missing source is a failure rather than a gap: renaming a seeder constant
 * breaks the build instead of quietly publishing a wrong number.
 */
class BuildDocumentation extends Command
{
    protected $signature = 'docs:build
        {--check : Resolve and validate without writing, for the test suite}';

    protected $description = 'Build the platform description from live sources';

    private const TEMPLATE = 'resources/docs/platform.html';

    private const OUTPUT = 'app/docs/platform.html';

    /**
     * Weights and subsets of the product\'s own typefaces, embedded so the page
     * looks like the thing it describes and cannot fall back silently.
     *
     * @var list<array{string, int, string}>
     */
    private const FONTS = [
        ['Archivo', 800, 'archivo-latin-800-normal-*.woff2'],
        ['Archivo', 800, 'archivo-latin-ext-800-normal-*.woff2'],
        ['Inter', 400, 'inter-latin-400-normal-*.woff2'],
        ['Inter', 400, 'inter-latin-ext-400-normal-*.woff2'],
        ['Inter', 600, 'inter-latin-600-normal-*.woff2'],
        ['Inter', 600, 'inter-latin-ext-600-normal-*.woff2'],
        ['JetBrains Mono', 500, 'jetbrains-mono-latin-500-normal-*.woff2'],
        ['JetBrains Mono', 500, 'jetbrains-mono-latin-ext-500-normal-*.woff2'],
    ];

    private const LATIN = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+2074,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';

    private const LATIN_EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';

    public function handle(): int
    {
        $template = base_path(self::TEMPLATE);

        if (! File::exists($template)) {
            $this->error('Missing template: '.self::TEMPLATE);

            return self::FAILURE;
        }

        try {
            $facts = $this->facts();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $html = File::get($template);

        if (! $this->option('check')) {
            try {
                $facts['fonts'] = $this->fonts();
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        } else {
            // The typefaces come out of the Vite build, which is not committed.
            // Validating them would fail on a clean checkout for a reason that
            // has nothing to do with the document going stale.
            $facts['fonts'] = '';
        }

        $filled = $this->fill($html, $facts);

        if ($filled === null) {
            return self::FAILURE;
        }

        foreach (array_diff(array_keys($facts), $this->placeholdersIn($html)) as $unused) {
            $this->warn("Fact never used in the template: {$unused}");
        }

        if ($this->option('check')) {
            $this->info('Every placeholder resolved against a live source.');

            return self::SUCCESS;
        }

        $output = storage_path(self::OUTPUT);
        File::ensureDirectoryExists(dirname($output));
        File::put($output, $filled);

        $this->info(sprintf('Wrote %s (%d KB)', self::OUTPUT, strlen($filled) / 1024));

        return self::SUCCESS;
    }

    /**
     * Every number and inventory the document states, each read from the thing
     * that makes it true.
     *
     * @return array<string, string>
     */
    private function facts(): array
    {
        $facts = [
            'sports.count' => $this->seederCount(SportSeeder::class, 'SPORTS'),
            'specialties.count' => $this->seederCount(SpecialtySeeder::class, 'SPECIALTIES'),
            'facilities.count' => $this->seederCount(FacilitySeeder::class, 'FACILITIES'),
            'levels.count' => $this->seederCount(LevelSeeder::class, 'LEVELS'),
            'surfaces.count' => $this->seederCount(SurfaceSeeder::class, 'SURFACES'),
            'ageGroups.count' => $this->seederCount(AgeGroupSeeder::class, 'GROUPS'),
            'counties.count' => (string) count((array) config('counties')),

            'seed.organizations' => (string) array_sum($this->constant(OrganizationSeeder::class, 'TARGET')),
            'seed.clubs' => (string) $this->constant(OrganizationProfileSeeder::class, 'CLUB_TARGET'),
            'seed.venues' => (string) $this->constant(SpaceSeeder::class, 'VENUE_TARGET'),
            'seed.practices' => (string) $this->constant(PracticeSeeder::class, 'PRACTICE_TARGET'),

            'types.count' => (string) count(OrganizationType::cases()),
            'ways.count' => (string) count(LocationWay::cases()),
            'professions.count' => (string) count(PersonProfession::cases()),

            'pages.count' => (string) $this->publicPageCount(),
            'admin.resources' => (string) $this->directoryCount('app/Filament/Admin/Resources'),
            'account.resources' => (string) $this->directoryCount('app/Filament/Organization/Resources'),

            'tests.count' => (string) $this->testCount(),
            'tests.files' => (string) $this->testFileCount(),
        ];

        foreach (Plan::cases() as $plan) {
            $facts["plans.{$plan->value}.price"] = (string) $this->planValue($plan, 'price');

            foreach (['sports', 'locations', 'spaces', 'services', 'gallery_images'] as $limit) {
                $value = config("plans.{$plan->value}.limits.{$limit}");
                $facts["plans.{$plan->value}.{$limit}"] = $value === null ? 'nelimitat' : (string) $value;
            }
        }

        foreach ($facts as $key => $value) {
            // Zero is a real price — the Free plan costs nothing. Everywhere else
            // it means the source moved and nothing was counted.
            $zeroIsReal = str_ends_with($key, '.price');

            if ($value === '' || ($value === '0' && ! $zeroIsReal)) {
                throw new RuntimeException("Fact resolved to nothing: {$key}. Its source moved or was removed.");
            }
        }

        return $facts;
    }

    private function planValue(Plan $plan, string $key): int|string
    {
        $value = config("plans.{$plan->value}.{$key}");

        if ($value === null) {
            throw new RuntimeException("Missing plan value: plans.{$plan->value}.{$key}");
        }

        return is_int($value) || is_string($value) ? $value : '';
    }

    /**
     * How many entries a seeded taxonomy ships with, read off the seeder rather
     * than the database: the document describes the product, not one deployment.
     *
     * @param  class-string  $seeder
     */
    private function seederCount(string $seeder, string $constant): string
    {
        return (string) count((array) $this->constant($seeder, $constant));
    }

    /**
     * @param  class-string  $class
     */
    private function constant(string $class, string $name): mixed
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->hasConstant($name)) {
            throw new RuntimeException("Missing constant {$class}::{$name}. Rename it in the fact list too.");
        }

        return $reflection->getConstant($name);
    }

    /**
     * Pages a visitor can reach without an account, which is what the document
     * walks through one by one.
     *
     * Defined mechanically rather than by a list of paths to skip: a public page
     * is a GET route whose controller sits directly in `App\Http\Controllers` —
     * so nothing under `Settings` counts — and which returns an Inertia response,
     * which is what separates a page from a JSON endpoint like the company
     * lookup. A new public controller raises the count on its own.
     */
    private function publicPageCount(): int
    {
        $pages = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $action = $route->getAction('controller');

            if (! is_string($action)) {
                continue;
            }

            // An invokable controller is registered without a method suffix.
            [$class, $method] = str_contains($action, '@')
                ? explode('@', $action)
                : [$action, '__invoke'];

            if (! str_starts_with($class, 'App\\Http\\Controllers\\')) {
                continue;
            }

            if (str_contains(substr($class, strlen('App\\Http\\Controllers\\')), '\\')) {
                continue;
            }

            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }

            if ($this->rendersPage((new ReflectionMethod($class, $method))->getReturnType())) {
                $pages++;
            }
        }

        return $pages;
    }

    /**
     * A union counts: the location page still renders a page even though it may
     * redirect when the slug it was given has been retired.
     */
    private function rendersPage(?ReflectionType $returns): bool
    {
        if ($returns instanceof ReflectionNamedType) {
            return $returns->getName() === InertiaResponse::class;
        }

        if ($returns instanceof ReflectionUnionType) {
            foreach ($returns->getTypes() as $type) {
                if ($this->rendersPage($type)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function directoryCount(string $path): int
    {
        $full = base_path($path);

        if (! File::isDirectory($full)) {
            throw new RuntimeException("Missing directory: {$path}");
        }

        return count(File::directories($full));
    }

    private function testCount(): int
    {
        $total = 0;

        foreach (File::allFiles(base_path('tests')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $total += preg_match_all('/^(test|it)\(/m', $file->getContents());
        }

        return $total;
    }

    private function testFileCount(): int
    {
        return collect(File::allFiles(base_path('tests')))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), 'Test.php'))
            ->count();
    }

    /**
     * The product's typefaces as data URIs. A strict content policy blocks font
     * CDNs on the published page, and a linked stylesheet would fall back to a
     * system face without saying so.
     */
    private function fonts(): string
    {
        $css = '';

        foreach (self::FONTS as [$family, $weight, $glob]) {
            $matches = glob(public_path('build/assets/'.$glob)) ?: [];
            $file = $matches[0] ?? null;

            if ($file === null) {
                throw new RuntimeException("Missing font: {$glob}. Run `npm run build` first.");
            }

            $range = str_contains($glob, '-ext-') ? self::LATIN_EXT : self::LATIN;
            $data = base64_encode((string) file_get_contents($file));

            $css .= sprintf(
                '@font-face{font-family:"%s";font-style:normal;font-weight:%d;font-display:swap;src:url(data:font/woff2;base64,%s) format("woff2");unicode-range:%s}'."\n",
                $family, $weight, $data, $range,
            );
        }

        return $css;
    }

    /**
     * @param  array<string, string>  $facts
     */
    private function fill(string $html, array $facts): ?string
    {
        $missing = array_diff($this->placeholdersIn($html), array_keys($facts));

        if ($missing !== []) {
            $this->error('Placeholders with no source: '.implode(', ', $missing));

            return null;
        }

        foreach ($facts as $key => $value) {
            $html = str_replace('{{ '.$key.' }}', $value, $html);
        }

        return $html;
    }

    /**
     * @return list<string>
     */
    private function placeholdersIn(string $html): array
    {
        preg_match_all('/\{\{ ([a-zA-Z][a-zA-Z0-9_.]*) \}\}/', $html, $matches);

        return array_values(array_unique($matches[1]));
    }
}
