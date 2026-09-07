<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Restrict access to one or more roles.
     *
     * Usage: middleware('role:admin,recruiter')
     * Users with role "both" match recruiter and analyst route groups.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        $allowed = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $userRoles = method_exists($user, 'effectiveRoles')
            ? $user->effectiveRoles()
            : [(string) $user->role];

        if ($allowed === [] || count(array_intersect($userRoles, $allowed)) === 0) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
