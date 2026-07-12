<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIp
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce the IP allowlist in production — never lock ourselves out locally.
        if (! app()->isProduction()) {
            return $next($request);
        }

        $allowedIp = Cache::remember('admin_allowed_ip', now()->addMinutes(5), function () {
            $ip = gethostbyname('stefanpandele.go.ro');

            // gethostbyname returns the exact hostname if DNS fails
            return $ip === 'stefanpandele.go.ro' ? null : $ip;
        });

        abort_unless($allowedIp && $request->ip() === $allowedIp, 404);

        return $next($request);
    }
}
