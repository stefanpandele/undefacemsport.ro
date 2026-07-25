<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToHomeArea
{
    /**
     * Redirect an authenticated user to their own home area when they land on
     * an area (`admin` | `club` | `user`) they don't belong to, instead of a
     * 403. Guests fall through to the area's own auth handling.
     */
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $this->belongsTo($user, $area)) {
            return redirect($user->homeUrl());
        }

        return $next($request);
    }

    private function belongsTo(User $user, string $area): bool
    {
        return match ($area) {
            'admin' => $user->is_admin || $user->isSuperAdmin(),
            'club' => $user->clubs()->exists(),
            'user' => $user->isConsumer(),
            default => true,
        };
    }
}
