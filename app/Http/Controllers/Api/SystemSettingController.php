<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    /**
     * Get the developer mode status (Public / Authenticated).
     */
    public function getDeveloperMode(): JsonResponse
    {
        return response()->json([
            'developer_mode' => SystemSetting::isDeveloperMode(),
        ]);
    }

    /**
     * Update developer mode (Super Admin only).
     */
    public function updateDeveloperMode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Super Administrator access is strictly required to modify developer mode settings.',
            ], 403);
        }

        $validated = $request->validate([
            'developer_mode' => 'required|boolean',
        ]);

        $oldMode = SystemSetting::isDeveloperMode();
        $newMode = (bool) $validated['developer_mode'];

        SystemSetting::set('developer_mode', $newMode);

        AuditLog::log(
            'DEVELOPER_MODE_TOGGLED',
            'system_settings',
            null,
            ['developer_mode' => $oldMode],
            ['developer_mode' => $newMode],
            $user->id
        );

        return response()->json([
            'message' => $newMode 
                ? 'Developer Mode has been ENABLED. Inspection and DevTools access are now allowed.' 
                : 'Developer Mode has been DISABLED. Inspection and DevTools are strictly restricted.',
            'developer_mode' => $newMode,
        ]);
    }
}
