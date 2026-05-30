<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to enforce 15-minute session timeout for mobile devices only.
 * Desktop sessions use standard Laravel session configuration.
 */
class MobileSessionTimeout
{
    /**
     * Mobile session timeout in minutes.
     */
    private const MOBILE_SESSION_TIMEOUT = 15;

    /**
     * Warning threshold in minutes before session expires.
     */
    private const SESSION_WARNING_THRESHOLD = 2;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not authenticated or no session available
        if (!$request->user() || !$request->hasSession()) {
            return $next($request);
        }

        // Only apply mobile timeout if device was detected as mobile
        $isMobile = $request->session()->get('is_mobile', false);
        
        if ($isMobile) {
            // Check and enforce session timeout
            $this->enforceMobileSessionTimeout($request);
        }

        return $next($request);
    }

    /**
     * Enforce mobile session timeout.
     *
     * @param Request $request
     * @return void
     */
    private function enforceMobileSessionTimeout(Request $request): void
    {
        $session = $request->session();
        $lastActivity = $session->get('mobile_last_activity');
        
        if ($lastActivity === null) {
            // First mobile request, initialize the activity timestamp
            $session->put('mobile_last_activity', now()->timestamp);
            $session->put('mobile_session_started', now()->timestamp);
            return;
        }

        $currentTime = now()->timestamp;
        $inactiveTime = $currentTime - $lastActivity;
        $timeoutSeconds = self::MOBILE_SESSION_TIMEOUT * 60;
        $warningSeconds = self::SESSION_WARNING_THRESHOLD * 60;

        // Check if session has expired
        if ($inactiveTime >= $timeoutSeconds) {
            // Session expired - invalidate and redirect to login
            try {
                $request->user()->currentAccessToken()->delete();
            } catch (\BadMethodCallException $e) {
                // TransientToken doesn't have delete method - ignore
            }
            $session->invalidate();
            $session->regenerateToken();

            abort(response()->json([
                'error' => 'SESSION_TIMEOUT',
                'message' => 'Your session has expired due to inactivity. Please log in again.',
            ], 401));
        }

        // Check if we're in the warning period
        $remainingTime = $timeoutSeconds - $inactiveTime;
        if ($remainingTime <= $warningSeconds && $remainingTime > 0) {
            // Add warning header for frontend to show countdown
            $request->headers->set('X-Session-Warning', 'true');
            $request->headers->set('X-Session-Timeout-At', (string) ($lastActivity + $timeoutSeconds));
        }

        // Update last activity timestamp
        $session->put('mobile_last_activity', $currentTime);
    }
}