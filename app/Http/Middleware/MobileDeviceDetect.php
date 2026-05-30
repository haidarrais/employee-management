<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to detect mobile devices based on User-Agent header.
 * Sets a session flag for device type detection.
 */
class MobileDeviceDetect
{
    /**
     * Common mobile device patterns in User-Agent strings.
     */
    private const MOBILE_PATTERNS = [
        '/Mobile/i',
        '/Android/i',
        '/webOS/i',
        '/iPhone/i',
        '/iPad/i',
        '/iPod/i',
        '/BlackBerry/i',
        '/Windows Phone/i',
        '/Opera Mini/i',
        '/Opera Mobi/i',
        '/Huawei/i',
        '/Samsung/i',
        '/Xiaomi/i',
        '/OPPO/i',
        '/Vivo/i',
        '/Realme/i',
        '/OnePlus/i',
        '/LG/i',
        '/Sony/i',
        '/Nokia/i',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Detect mobile device from User-Agent
        $isMobile = $this->detectMobileDevice($request);
        
        // Store device type in session (if session is available)
        if ($request->hasSession()) {
            $request->session()->put('is_mobile', $isMobile);
            $request->session()->put('device_detected_at', now()->timestamp);
        }
        
        // Add device type header for API responses
        $request->headers->set('X-Device-Type', $isMobile ? 'mobile' : 'desktop');
        
        return $next($request);
    }

    /**
     * Detect if the request is from a mobile device.
     *
     * @param Request $request
     * @return bool
     */
    private function detectMobileDevice(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        
        // Check User-Agent against mobile patterns
        foreach (self::MOBILE_PATTERNS as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        // Also check for common mobile HTTP headers
        if ($request->header('X-Mobile') === 'true' || 
            $request->header('X-Wap-Profile')) {
            return true;
        }
        
        return false;
    }
}