<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based Access Control Middleware.
 * 
 * Restricts access to routes based on user roles.
 * 
 * Usage:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,management')
 *   ->middleware('role:employee')
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$roles Allowed roles (comma-separated)
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        
        \Log::info('RoleMiddleware: user = ' . ($user ? $user->email : 'null'));

        // Check if user is authenticated
        if (!$user) {
            \Log::info('RoleMiddleware: user is null, returning 401');
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication required.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user has any of the allowed roles
        if (!$this->hasAnyRole($user, $roles)) {
            \Log::info('RoleMiddleware: role check failed for user ' . $user->role);
            // Log unauthorized access attempt
            app(\App\Services\AuditService::class)->log(
                'role_access_denied',
                $user->id,
                [
                    'required_roles' => implode(',', $roles),
                    'user_role' => $user->role,
                    'ip_address' => $request->ip(),
                    'route' => $request->route()?->uri(),
                ]
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'You do not have permission to access this resource.',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to access this resource.');
        }

        return $next($request);
    }

    /**
     * Check if user has any of the specified roles.
     * 
     * Administrator role bypasses all authorization checks.
     *
     * @param User $user
     * @param array<string> $roles
     * @return bool
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        // Administrator has unrestricted access (bypasses all authorization checks)
        if ($user->isAdmin()) {
            return true;
        }

        return in_array($user->role, $roles, true);
    }
}