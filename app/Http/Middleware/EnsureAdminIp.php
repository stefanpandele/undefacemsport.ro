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

        // Cloudflare sets CF-Connecting-IP to the real client IP; it can't be
        // spoofed through Cloudflare and doesn't depend on which upstream
        // proxies are trusted. On Laravel Cloud $request->ip() resolves to the
        // load balancer / a Cloudflare edge unless every hop is trusted.
        $clientIp = $request->header('CF-Connecting-IP') ?? $request->ip();

        // TEMP DEBUG — visit /admin/login?ipdebug=diag2026 to see the resolved
        // values directly in the response. Remove once the gate is confirmed.
        if ($request->query('ipdebug') === 'diag2026') {
            return response()->json([
                'client_ip' => $clientIp,
                'request_ip' => $request->ip(),
                'allowed_ip' => $allowedIp,
                'ips_chain' => $request->ips(),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
                'matches' => $allowedIp && $clientIp === $allowedIp,
            ]);
        }

        abort_unless($allowedIp && $clientIp === $allowedIp, 404);

        return $next($request);
    }
}
