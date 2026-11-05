<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Enforce role-based access control (ADMIN, LOGISTICS_OPERATOR, CUSTOMER).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Forbidden: insufficient role permissions.',
                    'required_roles' => $roles,
                ], 403);
            }

            abort(403, 'Unauthorized role for this corridor operation.');
        }

        return $next($request);
    }
}
