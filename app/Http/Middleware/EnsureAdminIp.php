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

        // Behind Cloudflare + Laravel Cloud's load balancer, $request->ip()
        // resolves to the load balancer, not the visitor. Cloudflare's
        // CF-Connecting-IP carries the real client IP.
        $clientIp = $request->header('CF-Connecting-IP') ?? $request->ip();

        abort_unless($allowedIp && $clientIp === $allowedIp, 404);

        return $next($request);
    }
}
