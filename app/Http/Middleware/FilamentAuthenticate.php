<?php

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as BaseAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Exceptions\HttpResponseException;

class FilamentAuthenticate extends BaseAuthenticate
{
    /**
     * Same as Filament's Authenticate, except a logged-in user who cannot
     * access this panel is redirected to their own home area instead of getting
     * a 403 (guests still fall through to the login redirect).
     *
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return; /** @phpstan-ignore-line */
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        $user = $guard->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel($panel)) {
            throw new HttpResponseException(
                redirect($user instanceof User ? $user->homeUrl() : Filament::getLoginUrl())
            );
        }

        abort_if(
            ! ($user instanceof FilamentUser) && config('app.env') !== 'local',
            403,
        );
    }
}
