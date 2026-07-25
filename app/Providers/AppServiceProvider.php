<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
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
}
