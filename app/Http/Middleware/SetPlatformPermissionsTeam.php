<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPlatformPermissionsTeam
{
    public function __construct(private PermissionRegistrar $permissions) {}

    /**
     * The /admin panel has no tenant, so Spatie's team context is never set for
     * it. Admin-staff roles live on the reserved "platform" team; this pins that
     * team so their permissions resolve on admin requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->permissions->setPermissionsTeamId(config('auth.platform_team_id'));

        return $next($request);
    }
}
