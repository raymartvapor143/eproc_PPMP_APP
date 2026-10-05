<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\SystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user's notifications and unread count
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SystemNotification::where('user_id', $user->id)
            ->with(['ppmp:id,uuid,tracking_number,ppmp_number,title,status,amendment_status,office_id,created_by,admin_received_at,head_received_at,budget_received_at,oppmo_received_at,twg_received_at,enduser_received_at'])
            ->latest('id');

        if ($request->boolean('all')) {
            $notifications = $query->take(100)->get();
        } else {
            $notifications = $query->take(20)->get();
        }

        $unreadCount = SystemNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = SystemNotification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        SystemNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * Delete / Dismiss single notification
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = SystemNotification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json(['message' => 'Notification dismissed.']);
    }

    /**
     * Clear all notifications for user
     */
    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();

        SystemNotification::where('user_id', $user->id)->delete();

        return response()->json(['message' => 'All notifications cleared.']);
    }

    /**
     * List all offices (for forms/filters)
     */
    public function offices(): JsonResponse
    {
        return response()->json(Office::with('head')->get());
    }
}
