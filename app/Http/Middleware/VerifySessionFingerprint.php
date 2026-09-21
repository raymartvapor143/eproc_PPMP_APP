<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifySessionFingerprint
{
    /**
     * Handle an incoming request.
     * Prevents session hijacking by validating the client's User-Agent fingerprint.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate if session exists and user is authenticated
        if ($request->hasSession() && Auth::check()) {
            $session = $request->session();
            $currentFingerprint = hash('sha256', (string) $request->userAgent());

            if (!$session->has('_client_fingerprint')) {
                // Initialize fingerprint if not present
                $session->put('_client_fingerprint', $currentFingerprint);
            } else {
                $storedFingerprint = $session->get('_client_fingerprint');

                if (!hash_equals($storedFingerprint, $currentFingerprint)) {
                    $user = Auth::user();

                    // Suspicious user-agent change detected: potential stolen cookie / session hijacking
                    AuditLog::log(
                        'SESSION_HIJACK_DETECTED',
                        'users',
                        $user?->id,
                        null,
                        [
                            'user_id' => $user?->id,
                            'ip_address' => $request->ip(),
                            'previous_user_agent' => $session->get('_client_user_agent_preview'),
                            'new_user_agent' => $request->userAgent(),
                        ],
                        $user?->id
                    );

                    Auth::guard('web')->logout();
                    $session->invalidate();
                    $session->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'Security alert: Session invalidated due to client environment mismatch.',
                            'code' => 'SESSION_HIJACK_PREVENTED'
                        ], 401);
                    }

                    return redirect('/login');
                }
            }
        }

        return $next($request);
    }
}
