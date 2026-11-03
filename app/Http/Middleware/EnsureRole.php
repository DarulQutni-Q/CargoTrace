<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side role gate. Hiding interface elements is never authorization;
 * every restricted route passes through here or a policy.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && in_array($user->role->value, $roles, true),
            403,
        );

        return $next($request);
    }
}
