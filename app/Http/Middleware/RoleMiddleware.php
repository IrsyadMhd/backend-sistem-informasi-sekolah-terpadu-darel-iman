<?php

namespace App\Http\Middleware;

use App\Support\RoleName;
use Closure;
use Spatie\Permission\Middleware\RoleMiddleware as SpatieRoleMiddleware;

class RoleMiddleware extends SpatieRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Provides Super Admin global role bypass aligning with the repository's
     * Gate::before authorization policy, while preserving standard Spatie role
     * validation for all other user roles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|array  $role
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $role, $guard = null)
    {
        $authGuard = auth($guard);

        if ($authGuard->check()) {
            $user = $authGuard->user();

            // Super Admin global role bypass aligning with repository Gate::before policy
            if (RoleName::userHasAny($user, ['Super Admin'])) {
                return $next($request);
            }
        }

        return parent::handle($request, $next, $role, $guard);
    }
}
