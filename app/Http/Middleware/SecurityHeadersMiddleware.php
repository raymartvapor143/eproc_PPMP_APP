<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and attach defensive HTTP security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking by forbidding embedding in foreign iframes
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME-type confusion / sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Legacy browser XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Protect privacy in outbound referrer headers
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict unnecessary browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
