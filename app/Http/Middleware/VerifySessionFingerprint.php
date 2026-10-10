<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\SystemSetting;
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
                $session->put('_client_user_agent_preview', $request->userAgent());
            } else {
                $storedFingerprint = $session->get('_client_fingerprint');

                if (!hash_equals($storedFingerprint, $currentFingerprint)) {
                    // When Developer Mode is ENABLED, developers and super admins are actively inspecting,
                    // using DevTools, testing responsive mobile viewports, or simulating devices.
                    // Adapt the session fingerprint to the current developer agent instead of kicking the user out.
                    if (SystemSetting::isDeveloperMode()) {
                        $session->put('_client_fingerprint', $currentFingerprint);
                        $session->put('_client_user_agent_preview', $request->userAgent());
                        return $next($request);
                    }

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
