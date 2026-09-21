<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * List users with office information (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $users = User::with('office')->orderBy('name')->get();

        return response()->json($users);
    }

    /**
     * Change a user's password (Admin only)
     */
    public function changePassword(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $targetUser = User::findOrFail($id);

        $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $newPassword = $request->input('password');

        $targetUser->update([
            'password' => Hash::make($newPassword),
        ]);

        AuditLog::log(
            'USER_PASSWORD_RESET_BY_ADMIN',
            'users',
            $targetUser->id,
            null,
            ['user_id' => $targetUser->id, 'email' => $targetUser->email],
            $admin->id
        );

        return response()->json([
            'message' => "Password for {$targetUser->name} has been updated successfully.",
        ]);
    }

    /**
     * Approve a pending user account (Admin only)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $targetUser = User::findOrFail($id);
        $targetUser->update(['is_active' => true]);

        AuditLog::log(
            'USER_APPROVED',
            'users',
            $targetUser->id,
            null,
            ['approved_user_id' => $targetUser->id, 'email' => $targetUser->email],
            $admin->id
        );

        return response()->json([
            'message' => "Account for {$targetUser->name} has been approved and activated.",
            'user' => $targetUser->load('office'),
        ]);
    }

    /**
     * Toggle active/inactive status (Admin only)
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $targetUser = User::findOrFail($id);

        if ($targetUser->id === $admin->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $newStatus = !$targetUser->is_active;
        $targetUser->update(['is_active' => $newStatus]);

        AuditLog::log(
            $newStatus ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
            'users',
            $targetUser->id,
            null,
            ['user_id' => $targetUser->id, 'is_active' => $newStatus],
            $admin->id
        );

        return response()->json([
            'message' => "User {$targetUser->name} is now " . ($newStatus ? 'active' : 'inactive') . ".",
            'user' => $targetUser->load('office'),
        ]);
    }

    /**
     * Delete user (Admin only)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $targetUser = User::findOrFail($id);

        if ($targetUser->id === $admin->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($targetUser->ppmps()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete user because they have submitted or created PPMP documents.'
            ], 422);
        }

        AuditLog::log(
            'USER_DELETED',
            'users',
            $targetUser->id,
            null,
            ['name' => $targetUser->name, 'email' => $targetUser->email],
            $admin->id
        );

        $targetUser->delete();

        return response()->json([
            'message' => "User {$targetUser->name} has been deleted successfully.",
        ]);
    }

    /**
     * Serve a user's signature file securely for authorized users
     */
    public function getSignature(Request $request, int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($id);

        // Access Control: Allow self, admin, or official procurement signatories/reviewers
        $isSelf = $currentUser->id === $targetUser->id;
        $isAdmin = $currentUser->isAdmin();
        $isOfficialSignatory = in_array($targetUser->role, ['head', 'budget_officer', 'oppmo', 'twg', 'admin'], true);

        if (!$isSelf && !$isAdmin && !$isOfficialSignatory) {
            // If target user is a regular end user, check if they prepared a PPMP that the current user has rights to view
            $sharesPpmp = \App\Models\PpmpSignature::where('user_id', $targetUser->id)
                ->whereHas('ppmp', function ($q) use ($currentUser) {
                    if ($currentUser->isHead()) {
                        $q->where('office_id', $currentUser->office_id);
                    } elseif ($currentUser->isBudgetOfficer() || $currentUser->isOppmo() || $currentUser->isTwg()) {
                        $q->whereNotIn('status', ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED']);
                    } else {
                        $q->where('created_by', $currentUser->id);
                    }
                })->exists();

            if (!$sharesPpmp) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to view this signature.'], 403);
            }
        }

        if (!$targetUser->signature_path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($targetUser->signature_path)) {
            return response()->json(['message' => 'No signature on file.'], 404);
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($targetUser->signature_path);

        return response()->file($fullPath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
