<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is inactive. Please contact your administrator.'], 403);
        }

        // Admin can access everything
        if ($user->isAdmin()) {
            return $next($request);
        }

        // authorized_staff acts on behalf of head
        if ($user->role === 'authorized_staff' && in_array('head', $roles, true)) {
            return $next($request);
        }

        if (empty($roles) || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden. You do not have the required role (' . implode(', ', $roles) . ') to perform this action.'
        ], 403);
    }
}
