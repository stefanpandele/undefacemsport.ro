<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDiacriticInsensitiveSelectSearch();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Behind Cloudflare/proxies the app may not detect HTTPS, which makes
        // redirects and generated URLs use http:// and get blocked as mixed
        // content. Force https everywhere except local development.
        if (! app()->isLocal()) {
            URL::forceScheme('https');
        }

        // Super admins (configured by email in config/auth.php) bypass every
        // authorization check, including Shield's tenant-scoped roles. Managed
        // by environment, not a database column.
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        Date::use(CarbonImmutable::class);

        // Create/edit modals hold unsaved form input, so a stray click on the
        // backdrop must not discard it. Confirmation modals keep the default.
        CreateAction::configureUsing(fn (CreateAction $action) => $action->closeModalByClickingAway(false));
        EditAction::configureUsing(fn (EditAction $action) => $action->closeModalByClickingAway(false));

        // Define the LocationMap Alpine component in the panel <head>, so it is
        // registered before Alpine evaluates the field's x-data inside modals.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => view('filament.location-map-scripts')->render(),
        );

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * A select whose options are a plain array is searched in the browser, with
     * a bare `label.includes(query)` — so "inot" never matches "Înot" and the
     * user has to guess which letters carry a diacritic. Handing every such
     * select a search callback moves the filtering to PHP, where both the query
     * and the label are folded to ASCII first.
     *
     * Selects built from `->relationship()` overwrite this callback with their
     * own; those search the database, where `utf8mb4_unicode_ci` already
     * ignores diacritics.
     */
    protected function configureDiacriticInsensitiveSelectSearch(): void
    {
        Select::configureUsing(function (Select $select): void {
            $select->getSearchResultsUsing(function (Select $component, string $search): array {
                $needle = Str::lower(Str::ascii($search));

                $matches = fn (string $label): bool => str_contains(Str::lower(Str::ascii($label)), $needle);

                $results = [];
                $remaining = $component->getOptionsLimit();

                foreach ($component->getOptions() as $value => $label) {
                    if ($remaining <= 0) {
                        break;
                    }

                    if (is_array($label)) {
                        $group = array_slice(array_filter($label, $matches), 0, $remaining, preserve_keys: true);

                        if ($group !== []) {
                            $results[$value] = $group;
                            $remaining -= count($group);
                        }

                        continue;
                    }

                    if ($matches((string) $label)) {
                        $results[$value] = $label;
                        $remaining--;
                    }
                }

                return $results;
            });
        });
    }
}
