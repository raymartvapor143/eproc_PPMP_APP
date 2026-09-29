<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpAttachment;
use App\Models\PpmpChangeLog;
use App\Models\PpmpItem;
use App\Models\PpmpReview;
use App\Models\PpmpRoute;
use App\Models\PpmpSignature;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PpmpWorkflowController extends Controller
{
    /**
     * 1. Submit PPMP to Office Head for endorsement (End User only)
     * DRAFT / HEAD_RETURNED -> HEAD_PENDING
     */
    public function submitToHead(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if ($ppmp->created_by !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Only the creator of this PPMP can submit it to the Office Head.'], 403);
        }

        if (!in_array($ppmp->status, ['DRAFT', 'HEAD_RETURNED'])) {
            return response()->json(['message' => "Cannot submit to Head in current status: {$ppmp->status}."], 422);
        }

        DB::transaction(function () use ($ppmp, $user) {
            $office = Office::find($ppmp->office_id);
            $targetHeadId = $office?->head_user_id;

            $ppmp->update([
                'status' => 'HEAD_PENDING',
                'head_submitted_at' => now(),
                'head_received_at' => null, // requires Head to click Receive
            ]);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $targetHeadId,
                'from_role' => 'end_user',
                'to_role' => 'head',
                'action' => 'SUBMITTED_TO_HEAD',
                'status' => 'HEAD_PENDING',
                'remarks' => 'Submitted to Office Head for approval and endorsement.',
                'submitted_at' => now(),
            ]);

            if ($targetHeadId) {
                SystemNotification::notify(
                    $targetHeadId,
                    'PPMP Submitted for Endorsement',
                    "PPMP No. {$ppmp->ppmp_number} ({$ppmp->title}) was submitted by {$user->name} for review.",
                    $ppmp->id,
                    'info'
                );
            }

            // Also notify any active Authorized Staff of this office
            $authStaffUserIds = User::where('office_id', $ppmp->office_id)
                ->where('role', 'authorized_staff')
                ->where('is_active', true)
                ->where('id', '!=', $targetHeadId)
                ->pluck('id');

            foreach ($authStaffUserIds as $staffId) {
                SystemNotification::notify(
                    $staffId,
                    'PPMP Submitted for Endorsement (Acting Head)',
                    "PPMP No. {$ppmp->ppmp_number} ({$ppmp->title}) was submitted by {$user->name} for endorsement on behalf of the Office Head.",
                    $ppmp->id,
                    'info'
                );
            }

            AuditLog::log('PPMP_SUBMITTED_TO_HEAD', 'ppmps', $ppmp->id, null, ['status' => 'HEAD_PENDING'], $user->id);
        });

        return response()->json([
            'message' => 'PPMP submitted to Office Head successfully.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 2a. Head Approves PPMP
     * HEAD_PENDING -> HEAD_APPROVED
     */
    public function headApprove(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isAdmin()) {
            if (!$user->isHead()) {
                return response()->json(['message' => 'Only the Office Head or Administrator can approve this PPMP.'], 403);
            }
            if ($user->office_id !== $ppmp->office_id) {
                return response()->json(['message' => 'Unauthorized. You may only approve PPMPs from your assigned office.'], 403);
            }
        }

        if ($ppmp->status !== 'HEAD_PENDING') {
            return response()->json(['message' => "PPMP is not awaiting Head approval (Current status: {$ppmp->status})."], 422);
        }

        DB::transaction(function () use ($ppmp, $user) {
            $now = now();
            $receivedAt = $ppmp->head_received_at ?? $now;

            $ppmp->update([
                'status' => 'HEAD_APPROVED',
                'head_approved_at' => $now,
                'head_received_at' => $receivedAt,
                'enduser_received_at' => null, // Creator receives notification and document
            ]);

            // Mark the incoming submission route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            // Create Head Electronic Signature Indicator
            PpmpSignature::updateOrCreate(
                ['ppmp_id' => $ppmp->id, 'role' => 'head'],
                [
                    'user_id' => $user->id,
                    'signature_type' => 'full_esignature',
                    'signature_indicator' => 'ESIG-HEAD-' . strtoupper(substr(md5($user->id . $ppmp->id . $now), 0, 8)),
                    'signer_name' => $user->name,
                    'signer_designation' => $user->designation ?? 'Head of Procuring Entity / Office Head',
                    'signed_at' => $now,
                ]
            );

            // Log Review
            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'head',
                'action' => 'approve',
                'remarks' => 'Approved and endorsed by Office Head.',
                'status_before' => 'HEAD_PENDING',
                'status_after' => 'HEAD_APPROVED',
                'submitted_at' => $ppmp->head_submitted_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Route back to End User
            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'head',
                'to_role' => 'end_user',
                'action' => 'HEAD_APPROVED',
                'status' => 'HEAD_APPROVED',
                'remarks' => 'Endorsed by Office Head. Ready for formal review submission.',
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            SystemNotification::notify(
                $ppmp->created_by,
                'PPMP Approved by Office Head',
                "PPMP No. {$ppmp->ppmp_number} has been approved and endorsed by Office Head {$user->name}. You may now submit it for formal review.",
                $ppmp->id,
                'success'
            );

            AuditLog::log('PPMP_HEAD_APPROVED', 'ppmps', $ppmp->id, null, ['status' => 'HEAD_APPROVED'], $user->id);
        });

        return response()->json([
            'message' => 'PPMP approved and endorsed successfully.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 2b. Head Returns PPMP with corrections
     * HEAD_PENDING -> HEAD_RETURNED
     */
    public function headReturn(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isAdmin()) {
            if (!$user->isHead()) {
                return response()->json(['message' => 'Only the Office Head or Administrator can return this PPMP.'], 403);
            }
            if ($user->office_id !== $ppmp->office_id) {
                return response()->json(['message' => 'Unauthorized. You may only return PPMPs from your assigned office.'], 403);
            }
        }

        if ($ppmp->status !== 'HEAD_PENDING') {
            return response()->json(['message' => "PPMP is not awaiting Head action."], 422);
        }

        $validated = $request->validate([
            'remarks' => 'required|string|min:3',
            'field_changes' => 'nullable|array',
        ]);

        DB::transaction(function () use ($ppmp, $user, $validated) {
            $now = now();
            $receivedAt = $ppmp->head_received_at ?? $now;

            $ppmp->update([
                'status' => 'HEAD_RETURNED',
                'enduser_received_at' => null,
            ]);

            // Mark the incoming route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            // Save any field modifications
            $this->applyFieldModifications($ppmp, $validated['field_changes'] ?? [], $user, 'head');

            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'head',
                'action' => 'return',
                'remarks' => $validated['remarks'],
                'status_before' => 'HEAD_PENDING',
                'status_after' => 'HEAD_RETURNED',
                'submitted_at' => $ppmp->head_submitted_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'head',
                'to_role' => 'end_user',
                'action' => 'HEAD_RETURNED',
                'status' => 'HEAD_RETURNED',
                'remarks' => $validated['remarks'],
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            SystemNotification::notify(
                $ppmp->created_by,
                'PPMP Returned by Office Head',
                "PPMP No. {$ppmp->ppmp_number} was returned with comments: " . Str::limit($validated['remarks'], 100),
                $ppmp->id,
                'warning'
            );

            AuditLog::log('PPMP_HEAD_RETURNED', 'ppmps', $ppmp->id, null, ['remarks' => $validated['remarks']], $user->id);
        });

        return response()->json([
            'message' => 'PPMP returned to end-user for revision.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 3. Submit PPMP for formal government review
     * HEAD_APPROVED / RETURNED -> BUDGET_OFFICER_REVIEW (or appropriate reviewer)
     */
    public function submitForReview(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if ($ppmp->created_by !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Only the creator can submit this PPMP for review.'], 403);
        }

        $allowedStatuses = [
            'HEAD_APPROVED',
            'BUDGET_OFFICER_RETURNED',
            'OPPMO_RETURNED',
            'TWG_RETURNED',
        ];

        if (!in_array($ppmp->status, $allowedStatuses)) {
            return response()->json(['message' => "PPMP cannot be submitted for review from status {$ppmp->status}."], 422);
        }

        // Determine destination:
        // If scope is ATTACHMENT_LIST only, TWG is the only reviewer (Budget Officer and OPPMO are skipped).
        $isAttachmentOnly = $ppmp->amendment_scope === 'ATTACHMENT_LIST';

        if ($isAttachmentOnly) {
            $nextStatus = 'TWG_REVIEW';
            $nextRole = 'twg';
        } else {
            // Determine destination: if returned by OPPMO or TWG, route back directly or default to BUDGET_OFFICER_REVIEW
            $nextStatus = match ($ppmp->status) {
                'OPPMO_RETURNED' => 'OPPMO_REVIEW',
                'TWG_RETURNED' => 'TWG_REVIEW',
                default => 'BUDGET_OFFICER_REVIEW',
            };

            $nextRole = match ($nextStatus) {
                'OPPMO_REVIEW' => 'oppmo',
                'TWG_REVIEW' => 'twg',
                default => 'budget_officer',
            };
        }

        DB::transaction(function () use ($ppmp, $user, $nextStatus, $nextRole) {
            $now = now();
            $receivedAt = $ppmp->enduser_received_at ?? $now;

            // Mark the returned / head approved route to end_user as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            $updateData = [
                'status' => $nextStatus,
                'review_submitted_at' => $now,
            ];

            if ($nextStatus === 'BUDGET_OFFICER_REVIEW') {
                $updateData['budget_received_at'] = null;
            } elseif ($nextStatus === 'OPPMO_REVIEW') {
                $updateData['oppmo_received_at'] = null;
            } elseif ($nextStatus === 'TWG_REVIEW') {
                $updateData['twg_received_at'] = null;
            }

            $ppmp->update($updateData);

            $reviewerUser = User::where('role', $nextRole)->where('is_active', true)->first();

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $reviewerUser?->id,
                'from_role' => 'end_user',
                'to_role' => $nextRole,
                'action' => 'SUBMITTED_FOR_REVIEW',
                'status' => $nextStatus,
                'remarks' => "Submitted for review to {$nextRole}.",
                'submitted_at' => $now,
            ]);

            if ($reviewerUser) {
                SystemNotification::notify(
                    $reviewerUser->id,
                    'PPMP Submitted for Review',
                    "PPMP No. {$ppmp->ppmp_number} ({$ppmp->title}) is ready for your review.",
                    $ppmp->id,
                    'info'
                );
            }

            AuditLog::log('PPMP_SUBMITTED_FOR_REVIEW', 'ppmps', $ppmp->id, null, ['target' => $nextStatus], $user->id);
        });

        return response()->json([
            'message' => 'PPMP submitted for formal review successfully.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 4a. Budget Officer Approves
     * BUDGET_OFFICER_REVIEW -> OPPMO_REVIEW
     */
    public function budgetApprove(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isBudgetOfficer() && !$user->isAdmin()) {
            return response()->json(['message' => 'Only the Provincial Budget Officer or Admin can certify budget.'], 403);
        }

        if ($ppmp->status !== 'BUDGET_OFFICER_REVIEW') {
            return response()->json(['message' => "PPMP is not awaiting budget officer review."], 422);
        }

        DB::transaction(function () use ($ppmp, $user) {
            $now = now();
            $receivedAt = $ppmp->budget_received_at ?? $now;

            $ppmp->update([
                'status' => 'OPPMO_REVIEW',
                'budget_approved_at' => $now,
                'budget_received_at' => $receivedAt,
                'oppmo_received_at' => null, // Next reviewer must receive
            ]);

            // Mark the incoming submission route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            PpmpSignature::updateOrCreate(
                ['ppmp_id' => $ppmp->id, 'role' => 'budget_officer'],
                [
                    'user_id' => $user->id,
                    'signature_type' => 'initial_indicator',
                    'signature_indicator' => 'INIT-PBO-' . strtoupper(substr(md5($user->id . $ppmp->id . $now), 0, 8)),
                    'signer_name' => $user->name,
                    'signer_designation' => $user->designation ?? 'Provincial Budget Officer',
                    'signed_at' => $now,
                ]
            );

            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'budget_officer',
                'action' => 'approve',
                'remarks' => 'Budget requirements certified and verified.',
                'status_before' => 'BUDGET_OFFICER_REVIEW',
                'status_after' => 'OPPMO_REVIEW',
                'submitted_at' => $ppmp->review_submitted_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $oppmoUser = User::where('role', 'oppmo')->where('is_active', true)->first();

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $oppmoUser?->id,
                'from_role' => 'budget_officer',
                'to_role' => 'oppmo',
                'action' => 'BUDGET_APPROVED',
                'status' => 'OPPMO_REVIEW',
                'remarks' => 'Certified budgetary requirement. Routed to OPPMO.',
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            if ($oppmoUser) {
                SystemNotification::notify(
                    $oppmoUser->id,
                    'PPMP Awaiting OPPMO Review',
                    "PPMP No. {$ppmp->ppmp_number} was certified by Budget Office and routed to OPPMO.",
                    $ppmp->id,
                    'info'
                );
            }

            AuditLog::log('PPMP_BUDGET_APPROVED', 'ppmps', $ppmp->id, null, ['status' => 'OPPMO_REVIEW'], $user->id);
        });

        return response()->json([
            'message' => 'PPMP certified by Budget Officer and forwarded to OPPMO.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 4b. Budget Officer Returns
     * BUDGET_OFFICER_REVIEW -> BUDGET_OFFICER_RETURNED
     */
    public function budgetReturn(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isBudgetOfficer() && !$user->isAdmin()) {
            return response()->json(['message' => 'Only the Budget Officer or Admin can perform this action.'], 403);
        }

        if ($ppmp->status !== 'BUDGET_OFFICER_REVIEW') {
            return response()->json(['message' => "PPMP is not awaiting budget officer review."], 422);
        }

        $validated = $request->validate([
            'remarks' => 'required|string|min:3',
            'field_changes' => 'nullable|array',
        ]);

        DB::transaction(function () use ($ppmp, $user, $validated) {
            $now = now();
            $receivedAt = $ppmp->budget_received_at ?? $now;

            $ppmp->update([
                'status' => 'BUDGET_OFFICER_RETURNED',
                'enduser_received_at' => null,
            ]);

            // Mark incoming route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            $this->applyFieldModifications($ppmp, $validated['field_changes'] ?? [], $user, 'budget_officer');

            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'budget_officer',
                'action' => 'return',
                'remarks' => $validated['remarks'],
                'status_before' => 'BUDGET_OFFICER_REVIEW',
                'status_after' => 'BUDGET_OFFICER_RETURNED',
                'submitted_at' => $ppmp->review_submitted_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'budget_officer',
                'to_role' => 'end_user',
                'action' => 'BUDGET_RETURNED',
                'status' => 'BUDGET_OFFICER_RETURNED',
                'remarks' => $validated['remarks'],
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            SystemNotification::notify(
                $ppmp->created_by,
                'PPMP Returned by Budget Officer',
                "PPMP No. {$ppmp->ppmp_number} returned: " . Str::limit($validated['remarks'], 100),
                $ppmp->id,
                'warning'
            );

            AuditLog::log('PPMP_BUDGET_RETURNED', 'ppmps', $ppmp->id, null, ['remarks' => $validated['remarks']], $user->id);
        });

        return response()->json([
            'message' => 'PPMP returned by Budget Officer.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 5a. OPPMO Approves
     * OPPMO_REVIEW -> TWG_REVIEW
     */
    public function oppmoApprove(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isOppmo() && !$user->isAdmin()) {
            return response()->json(['message' => 'Only OPPMO or Admin can approve this stage.'], 403);
        }

        if ($ppmp->status !== 'OPPMO_REVIEW') {
            return response()->json(['message' => "PPMP is not awaiting OPPMO review."], 422);
        }

        $isScopePpmpAppOnly = $ppmp->amendment_scope === 'PPMP_APP';

        DB::transaction(function () use ($ppmp, $user, $isScopePpmpAppOnly) {
            $now = now();
            $receivedAt = $ppmp->oppmo_received_at ?? $now;

            $nextStatus = $isScopePpmpAppOnly ? 'READY_TO_PRINT' : 'TWG_REVIEW';

            $updateData = [
                'status' => $nextStatus,
                'oppmo_approved_at' => $now,
                'oppmo_received_at' => $receivedAt,
            ];

            if ($isScopePpmpAppOnly) {
                $updateData['ready_to_print_at'] = $now;
                $updateData['enduser_received_at'] = null;
            } else {
                $updateData['twg_received_at'] = null;
            }

            $ppmp->update($updateData);

            // Mark incoming route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            PpmpSignature::updateOrCreate(
                ['ppmp_id' => $ppmp->id, 'role' => 'oppmo'],
                [
                    'user_id' => $user->id,
                    'signature_type' => 'initial_indicator',
                    'signature_indicator' => 'INIT-OPPMO-' . strtoupper(substr(md5($user->id . $ppmp->id . $now), 0, 8)),
                    'signer_name' => $user->name,
                    'signer_designation' => $user->designation ?? 'OPPMO Officer',
                    'signed_at' => $now,
                ]
            );

            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'oppmo',
                'action' => 'approve',
                'remarks' => $isScopePpmpAppOnly
                    ? 'Procurement management review approved. Amendment scope is PPMP/APP only; document is fully approved and ready to print.'
                    : 'Procurement management review approved.',
                'status_before' => 'OPPMO_REVIEW',
                'status_after' => $nextStatus,
                'submitted_at' => $ppmp->budget_approved_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if ($isScopePpmpAppOnly) {
                PpmpRoute::create([
                    'ppmp_id' => $ppmp->id,
                    'from_user_id' => $user->id,
                    'to_user_id' => $ppmp->created_by,
                    'from_role' => 'oppmo',
                    'to_role' => 'end_user',
                    'action' => 'READY_TO_PRINT',
                    'status' => 'READY_TO_PRINT',
                    'remarks' => 'Approved by OPPMO (PPMP/APP Scope). Review workflow complete and document is ready to print.',
                    'submitted_at' => $now,
                    'acted_at' => $now,
                ]);

                SystemNotification::notify(
                    $ppmp->created_by,
                    'PPMP Approved & Ready to Print',
                    "PPMP No. {$ppmp->ppmp_number} ({$ppmp->title}) has received full OPPMO approval and is now READY TO PRINT.",
                    $ppmp->id,
                    'success'
                );

                AuditLog::log('PPMP_OPPMO_APPROVED_FINAL', 'ppmps', $ppmp->id, null, ['status' => 'READY_TO_PRINT'], $user->id);
            } else {
                $twgUser = User::where('role', 'twg')->where('is_active', true)->first();

                PpmpRoute::create([
                    'ppmp_id' => $ppmp->id,
                    'from_user_id' => $user->id,
                    'to_user_id' => $twgUser?->id,
                    'from_role' => 'oppmo',
                    'to_role' => 'twg',
                    'action' => 'OPPMO_APPROVED',
                    'status' => 'TWG_REVIEW',
                    'remarks' => 'Approved by OPPMO. Forwarded to BAC-TWG.',
                    'submitted_at' => $now,
                    'acted_at' => $now,
                ]);

                if ($twgUser) {
                    SystemNotification::notify(
                        $twgUser->id,
                        'PPMP Awaiting TWG Review',
                        "PPMP No. {$ppmp->ppmp_number} was approved by OPPMO and is now awaiting BAC-TWG review.",
                        $ppmp->id,
                        'info'
                    );
                }

                AuditLog::log('PPMP_OPPMO_APPROVED', 'ppmps', $ppmp->id, null, ['status' => 'TWG_REVIEW'], $user->id);
            }
        });

        $respMsg = $isScopePpmpAppOnly
            ? 'PPMP approved by OPPMO and is now ready for printing.'
            : 'PPMP approved by OPPMO and forwarded to TWG.';

        return response()->json([
            'message' => $respMsg,
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 5b. OPPMO Returns
     * OPPMO_REVIEW -> OPPMO_RETURNED
     */
    public function oppmoReturn(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isOppmo() && !$user->isAdmin()) {
            return response()->json(['message' => 'Only OPPMO or Admin can return this PPMP.'], 403);
        }

        if ($ppmp->status !== 'OPPMO_REVIEW') {
            return response()->json(['message' => "PPMP is not awaiting OPPMO review."], 422);
        }

        $validated = $request->validate([
            'remarks' => 'required|string|min:3',
            'field_changes' => 'nullable|array',
        ]);

        DB::transaction(function () use ($ppmp, $user, $validated) {
            $now = now();
            $receivedAt = $ppmp->oppmo_received_at ?? $now;

            $ppmp->update([
                'status' => 'OPPMO_RETURNED',
                'enduser_received_at' => null,
            ]);

            // Mark incoming route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            $this->applyFieldModifications($ppmp, $validated['field_changes'] ?? [], $user, 'oppmo');

            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'oppmo',
                'action' => 'return',
                'remarks' => $validated['remarks'],
                'status_before' => 'OPPMO_REVIEW',
                'status_after' => 'OPPMO_RETURNED',
                'submitted_at' => $ppmp->budget_approved_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'oppmo',
                'to_role' => 'end_user',
                'action' => 'OPPMO_RETURNED',
                'status' => 'OPPMO_RETURNED',
                'remarks' => $validated['remarks'],
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            SystemNotification::notify(
                $ppmp->created_by,
                'PPMP Returned by OPPMO',
                "PPMP No. {$ppmp->ppmp_number} returned: " . Str::limit($validated['remarks'], 100),
                $ppmp->id,
                'warning'
            );

            AuditLog::log('PPMP_OPPMO_RETURNED', 'ppmps', $ppmp->id, null, ['remarks' => $validated['remarks']], $user->id);
        });

        return response()->json([
            'message' => 'PPMP returned by OPPMO.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 6a. TWG Approves (Final Technical Review)
     * TWG_REVIEW -> READY_TO_PRINT
     */
    public function twgApprove(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isTwg() && !$user->isAdmin()) {
            return response()->json(['message' => 'Only BAC-TWG or Admin can approve this stage.'], 403);
        }

        if ($ppmp->status !== 'TWG_REVIEW') {
            return response()->json(['message' => "PPMP is not awaiting TWG review."], 422);
        }

        DB::transaction(function () use ($ppmp, $user) {
            $now = now();
            $receivedAt = $ppmp->twg_received_at ?? $now;

            $ppmp->update([
                'status' => 'READY_TO_PRINT',
                'twg_approved_at' => $now,
                'twg_received_at' => $receivedAt,
                'ready_to_print_at' => $now,
                'enduser_received_at' => null,
            ]);

            // Mark incoming route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            PpmpSignature::updateOrCreate(
                ['ppmp_id' => $ppmp->id, 'role' => 'twg'],
                [
                    'user_id' => $user->id,
                    'signature_type' => 'full_esignature',
                    'signature_indicator' => 'ESIG-TWG-' . strtoupper(substr(md5($user->id . $ppmp->id . $now), 0, 8)),
                    'signer_name' => $user->name,
                    'signer_designation' => $user->designation ?? 'BAC Technical Working Group (TWG) Member',
                    'signed_at' => $now,
                ]
            );

            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'twg',
                'action' => 'approve',
                'remarks' => 'BAC-TWG final technical approval granted. PPMP is approved and ready to print.',
                'status_before' => 'TWG_REVIEW',
                'status_after' => 'READY_TO_PRINT',
                'submitted_at' => $ppmp->oppmo_approved_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'twg',
                'to_role' => 'end_user',
                'action' => 'READY_TO_PRINT',
                'status' => 'READY_TO_PRINT',
                'remarks' => 'All review approvals completed. Document is ready to print.',
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            SystemNotification::notify(
                $ppmp->created_by,
                'PPMP Approved & Ready to Print',
                "Congratulations! PPMP No. {$ppmp->ppmp_number} ({$ppmp->title}) has received full BAC-TWG technical approval and is now READY TO PRINT.",
                $ppmp->id,
                'success'
            );

            AuditLog::log('PPMP_TWG_APPROVED', 'ppmps', $ppmp->id, null, ['status' => 'READY_TO_PRINT'], $user->id);
        });

        return response()->json([
            'message' => 'PPMP approved by BAC-TWG and marked as READY TO PRINT.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 6b. TWG Returns
     * TWG_REVIEW -> TWG_RETURNED
     */
    public function twgReturn(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if (!$user->isTwg() && !$user->isAdmin()) {
            return response()->json(['message' => 'Only BAC-TWG or Admin can return this PPMP.'], 403);
        }

        if ($ppmp->status !== 'TWG_REVIEW') {
            return response()->json(['message' => "PPMP is not awaiting TWG review."], 422);
        }

        $validated = $request->validate([
            'remarks' => 'required|string|min:3',
            'field_changes' => 'nullable|array',
        ]);

        DB::transaction(function () use ($ppmp, $user, $validated) {
            $now = now();
            $receivedAt = $ppmp->twg_received_at ?? $now;

            $ppmp->update([
                'status' => 'TWG_RETURNED',
                'enduser_received_at' => null,
            ]);

            // Mark incoming route as received
            $this->markLatestPendingRouteReceived($ppmp->id, $receivedAt);

            $this->applyFieldModifications($ppmp, $validated['field_changes'] ?? [], $user, 'twg');

            PpmpReview::create([
                'ppmp_id' => $ppmp->id,
                'reviewer_id' => $user->id,
                'reviewer_role' => 'twg',
                'action' => 'return',
                'remarks' => $validated['remarks'],
                'status_before' => 'TWG_REVIEW',
                'status_after' => 'TWG_RETURNED',
                'submitted_at' => $ppmp->oppmo_approved_at ?? $now,
                'received_at' => $receivedAt,
                'acted_at' => $now,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'twg',
                'to_role' => 'end_user',
                'action' => 'TWG_RETURNED',
                'status' => 'TWG_RETURNED',
                'remarks' => $validated['remarks'],
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            SystemNotification::notify(
                $ppmp->created_by,
                'PPMP Returned by BAC-TWG',
                "PPMP No. {$ppmp->ppmp_number} returned: " . Str::limit($validated['remarks'], 100),
                $ppmp->id,
                'warning'
            );

            AuditLog::log('PPMP_TWG_RETURNED', 'ppmps', $ppmp->id, null, ['remarks' => $validated['remarks']], $user->id);
        });

        return response()->json([
            'message' => 'PPMP returned by BAC-TWG.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * 7. Explicit Receive Document in current workflow stage
     */
    public function receive(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();
        $now = now();

        $updated = false;

        // Admin receiving an amendment / supplemental request
        if ($ppmp->amendment_status === 'PENDING_APPROVAL' && ($user->isAdmin() || $user->role === 'admin')) {
            $ppmp->update(['admin_received_at' => $now]);
            $updated = true;

            $this->markLatestPendingRouteReceived($ppmp->id, $now);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
                'from_role' => 'admin',
                'to_role' => 'admin',
                'action' => 'ADMIN_RECEIVED_REQUEST',
                'status' => $ppmp->status,
                'remarks' => 'Supplemental/Amendment request formally received by Administrator.',
                'submitted_at' => $now,
                'received_at' => $now,
                'acted_at' => $now,
            ]);
        } elseif ($ppmp->status === 'HEAD_PENDING' && (($user->isHead() && $user->office_id === $ppmp->office_id) || $user->isAdmin())) {
            $ppmp->update(['head_received_at' => $now]);
            $this->markLatestPendingRouteReceived($ppmp->id, $now);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
                'from_role' => 'head',
                'to_role' => 'head',
                'action' => 'HEAD_RECEIVED',
                'status' => 'HEAD_PENDING',
                'remarks' => 'PPMP document received and under Office Head review.',
                'submitted_at' => $now,
                'received_at' => $now,
                'acted_at' => $now,
            ]);
            $updated = true;
        } elseif ($ppmp->status === 'BUDGET_OFFICER_REVIEW' && ($user->isBudgetOfficer() || $user->isAdmin())) {
            $ppmp->update(['budget_received_at' => $now]);
            $this->markLatestPendingRouteReceived($ppmp->id, $now);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
                'from_role' => 'budget_officer',
                'to_role' => 'budget_officer',
                'action' => 'BUDGET_RECEIVED',
                'status' => 'BUDGET_OFFICER_REVIEW',
                'remarks' => 'PPMP document received and under Provincial Budget Officer review.',
                'submitted_at' => $now,
                'received_at' => $now,
                'acted_at' => $now,
            ]);
            $updated = true;
        } elseif ($ppmp->status === 'OPPMO_REVIEW' && ($user->isOppmo() || $user->isAdmin())) {
            $ppmp->update(['oppmo_received_at' => $now]);
            $this->markLatestPendingRouteReceived($ppmp->id, $now);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
                'from_role' => 'oppmo',
                'to_role' => 'oppmo',
                'action' => 'OPPMO_RECEIVED',
                'status' => 'OPPMO_REVIEW',
                'remarks' => 'PPMP document received and under OPPMO review.',
                'submitted_at' => $now,
                'received_at' => $now,
                'acted_at' => $now,
            ]);
            $updated = true;
        } elseif ($ppmp->status === 'TWG_REVIEW' && ($user->isTwg() || $user->isAdmin())) {
            $ppmp->update(['twg_received_at' => $now]);
            $this->markLatestPendingRouteReceived($ppmp->id, $now);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
                'from_role' => 'twg',
                'to_role' => 'twg',
                'action' => 'TWG_RECEIVED',
                'status' => 'TWG_REVIEW',
                'remarks' => 'PPMP document received and under BAC-TWG technical review.',
                'submitted_at' => $now,
                'received_at' => $now,
                'acted_at' => $now,
            ]);
            $updated = true;
        } elseif (in_array($ppmp->status, [
            'HEAD_APPROVED',
            'READY_TO_PRINT',
            'HEAD_RETURNED',
            'BUDGET_OFFICER_RETURNED',
            'OPPMO_RETURNED',
            'TWG_RETURNED'
        ]) && ($ppmp->created_by === $user->id || $user->isAdmin())) {
            $ppmp->update(['enduser_received_at' => $now]);
            $this->markLatestPendingRouteReceived($ppmp->id, $now);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
                'from_role' => 'end_user',
                'to_role' => 'end_user',
                'action' => 'END_USER_RECEIVED',
                'status' => $ppmp->status,
                'remarks' => 'PPMP document acknowledged and received by Implementing Unit.',
                'submitted_at' => $now,
                'received_at' => $now,
                'acted_at' => $now,
            ]);
            $updated = true;
        }

        if (!$updated) {
            return response()->json(['message' => 'No pending receipt action found for your account on this document.'], 400);
        }

        AuditLog::log('PPMP_RECEIVED', 'ppmps', $ppmp->id, null, ['status' => $ppmp->status, 'role' => $user->role], $user->id);

        return response()->json([
            'message' => 'Document received successfully.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * Mark the most recent route awaiting receipt for a PPMP as received
     */
    private function markLatestPendingRouteReceived(int $ppmpId, $timestamp = null): void
    {
        $timestamp = $timestamp ?? now();
        $pendingRoute = PpmpRoute::where('ppmp_id', $ppmpId)
            ->whereNull('received_at')
            ->latest('id')
            ->first();

        if ($pendingRoute) {
            $pendingRoute->update(['received_at' => $timestamp]);
        }
    }

    /**
     * 8a. Request Supplemental or Amendment (End User when READY_TO_PRINT)
     */
    public function requestAmendmentOrSupplemental(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if ($ppmp->created_by !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Only the owner of this PPMP can request a Supplemental or Amendment.'], 403);
        }

        if ($ppmp->status !== 'READY_TO_PRINT') {
            return response()->json(['message' => 'Only fully approved PPMPs (Ready to Print) can request Supplemental or Amendment.'], 422);
        }

        if ($ppmp->amendment_status === 'PENDING_APPROVAL') {
            return response()->json(['message' => 'An amendment or supplemental request is already pending administrator approval.'], 422);
        }

        $validated = $request->validate([
            'request_scope' => 'nullable|in:PPMP_APP,ATTACHMENT_LIST,ALL',
            'request_type' => 'nullable|in:SUPPLEMENTAL,AMENDMENT,ATTACHMENT_LIST',
            'reason' => 'required|string|min:5|max:2000',
            'letter_file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $scope = $validated['request_scope'] ?? 'PPMP_APP';
        $type = $validated['request_type'] ?? ($scope === 'ATTACHMENT_LIST' ? 'ATTACHMENT_LIST' : 'SUPPLEMENTAL');

        $uploadedFile = $request->file('letter_file');
        
        // Validate %PDF- magic bytes header to prevent malicious or disguised executable uploads
        $handle = fopen($uploadedFile->getRealPath(), 'rb');
        $header = fread($handle, 5);
        fclose($handle);
        if ($header !== '%PDF-') {
            return response()->json(['message' => 'Invalid file content. Must be a valid PDF document.'], 422);
        }

        $now = now();

        $ppmp = DB::transaction(function () use ($ppmp, $user, $validated, $uploadedFile, $scope, $type, $now) {
            // Save request letter in private disk
            $internalFilename = (string) Str::uuid() . '.pdf';
            $directory = 'ppmp/' . $ppmp->id;
            $path = $uploadedFile->storeAs($directory, $internalFilename, 'local');

            $attachment = PpmpAttachment::create([
                'uuid' => (string) Str::uuid(),
                'ppmp_id' => $ppmp->id,
                'uploaded_by' => $user->id,
                'original_filename' => basename($uploadedFile->getClientOriginalName()),
                'stored_filename' => $internalFilename,
                'storage_path' => $path,
                'mime_type' => 'application/pdf',
                'attachment_type' => 'REQUEST_LETTER',
                'file_size' => $uploadedFile->getSize(),
            ]);

            $ppmp->update([
                'amendment_status' => 'PENDING_APPROVAL',
                'amendment_type' => $type,
                'amendment_scope' => $scope,
                'amendment_reason' => $validated['reason'],
                'requested_amendment_type' => $type,
                'requested_amendment_scope' => $scope,
                'requested_amendment_reason' => $validated['reason'],
                'amendment_requested_at' => $now,
                'admin_received_at' => null, // Admin needs to receive request
            ]);

            $scopeLabel = match ($scope) {
                'PPMP_APP' => 'PPMP / APP Only',
                'ATTACHMENT_LIST' => 'PPMP List of Attachment Only',
                default => 'PPMP / APP & List of Attachment',
            };
            $typeLabel = match ($type) {
                'SUPPLEMENTAL' => 'Supplemental',
                'AMENDMENT' => 'Amendment',
                default => 'List of Attachment Update',
            };

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => null,
                'from_role' => 'end_user',
                'to_role' => 'admin',
                'action' => 'REQUESTED_' . $type,
                'status' => $ppmp->status,
                'remarks' => "Requested {$typeLabel} [Scope: {$scopeLabel}]: {$validated['reason']}\nAttached Letter: {$attachment->original_filename}",
                'submitted_at' => $now,
            ]);

            // Notify Admins
            $admins = User::where('role', 'admin')->where('is_active', true)->get();
            foreach ($admins as $admin) {
                SystemNotification::notify(
                    $admin->id,
                    "New {$typeLabel} Request",
                    "Office '{$ppmp->office?->name}' requested a {$typeLabel} ({$scopeLabel}) for PPMP No. {$ppmp->ppmp_number}.",
                    $ppmp->id,
                    'info'
                );
            }

            AuditLog::log('PPMP_AMENDMENT_REQUESTED', 'ppmps', $ppmp->id, null, [
                'type' => $type,
                'scope' => $scope,
                'reason' => $validated['reason'],
                'attachment_id' => $attachment->id,
            ], $user->id);

            return $ppmp;
        });

        return response()->json([
            'message' => 'Request submitted successfully and is awaiting Administrator approval.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser', 'attachments']),
        ]);
    }

    /**
     * 8b. Admin Approves Amendment / Supplemental Request
     * Marks baseline as Annual PPMP, forks a child PPMP with incremented ppmp_number, sets to DRAFT
     */
    public function approveAmendmentRequest(Request $request, string $uuid): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Only Administrators can approve amendment requests.'], 403);
        }

        $ppmp = Ppmp::with(['items', 'signatures', 'attachments'])->where('uuid', $uuid)->firstOrFail();

        if ($ppmp->amendment_status !== 'PENDING_APPROVAL') {
            return response()->json(['message' => 'This PPMP does not have a pending amendment/supplemental request.'], 422);
        }

        $type = $ppmp->requested_amendment_type ?: $ppmp->amendment_type ?: 'SUPPLEMENTAL';
        $scope = $ppmp->requested_amendment_scope ?: $ppmp->amendment_scope ?: 'ALL';
        $reason = $ppmp->requested_amendment_reason ?: $ppmp->amendment_reason;
        $now = now();

        $newPpmp = DB::transaction(function () use ($ppmp, $admin, $type, $scope, $reason, $now) {
            // Update amendment status on the baseline PPMP (only the root PPMP with no parent_id is the Annual PPMP)
            $ppmp->update([
                'amendment_status' => 'APPROVED',
                'amendment_approved_at' => $now,
                'is_annual' => empty($ppmp->parent_id),
            ]);

            // Preserve the existing PPMP number without auto-incrementing (+1); end user will manually edit PPMP No. if needed
            $ppmpNumber = $ppmp->ppmp_number;

            // Keep the exact same tracker number across amendments/supplementals
            $trackingNumber = $ppmp->tracking_number;

            $childPpmp = Ppmp::create([
                'uuid' => (string) Str::uuid(),
                'parent_id' => $ppmp->id,
                'tracking_number' => $trackingNumber,
                'ppmp_number' => $ppmpNumber,
                'office_id' => $ppmp->office_id,
                'implementing_unit' => $ppmp->implementing_unit,
                'created_by' => $ppmp->created_by,
                'title' => $ppmp->title,
                'account_code' => $ppmp->account_code,
                'fiscal_year' => $ppmp->fiscal_year,
                'plan_type' => $ppmp->plan_type,
                'is_annual' => false,
                'delivery_period' => $ppmp->delivery_period,
                'place_of_delivery' => $ppmp->place_of_delivery,
                'payment_method' => $ppmp->payment_method,
                'warranty_and_other_terms' => $ppmp->warranty_and_other_terms,
                'attachment_list_data' => $ppmp->attachment_list_data,
                'app_data' => $ppmp->app_data,
                'total_budget' => $ppmp->total_budget,
                'status' => 'DRAFT',
                'amendment_status' => null,
                'amendment_type' => $type,
                'amendment_scope' => $scope,
                'amendment_reason' => $reason,
                'prepared_at' => $now,
            ]);

            // Replicate line items
            foreach ($ppmp->items as $item) {
                PpmpItem::create([
                    'ppmp_id' => $childPpmp->id,
                    'item_no' => $item->item_no,
                    'description' => $item->description,
                    'project_type' => $item->project_type,
                    'quantity_size' => $item->quantity_size,
                    'procurement_mode' => $item->procurement_mode,
                    'pre_proc_conference' => $item->pre_proc_conference,
                    'start_date' => $item->start_date,
                    'end_date' => $item->end_date,
                    'delivery_period' => $item->delivery_period,
                    'source_of_fund' => $item->source_of_fund,
                    'estimated_budget' => $item->estimated_budget,
                    'supporting_docs_text' => $item->supporting_docs_text,
                    'remarks' => $item->remarks,
                ]);
            }

            // Copy non-letter attachments or link them
            foreach ($ppmp->attachments as $att) {
                if (Storage::disk('local')->exists($att->storage_path)) {
                    $newExt = pathinfo($att->stored_filename, PATHINFO_EXTENSION);
                    $newFilename = (string) Str::uuid() . '.' . $newExt;
                    $newDirectory = 'ppmp/' . $childPpmp->id;
                    $newPath = $newDirectory . '/' . $newFilename;

                    Storage::disk('local')->copy($att->storage_path, $newPath);

                    PpmpAttachment::create([
                        'uuid' => (string) Str::uuid(),
                        'ppmp_id' => $childPpmp->id,
                        'uploaded_by' => $att->uploaded_by,
                        'original_filename' => $att->original_filename,
                        'stored_filename' => $newFilename,
                        'storage_path' => $newPath,
                        'mime_type' => $att->mime_type,
                        'attachment_type' => $att->attachment_type,
                        'file_size' => $att->file_size,
                    ]);
                }
            }

            // For amended or supplemental PPMPs, reviewer signatures (budget_officer, oppmo, twg)
            // and head signature must NOT be copied from parent.
            // The new revision starts in DRAFT and must proceed through the review workflow before reviewer signatures/initials are inserted.
            // Only the end_user (creator) signature is preserved.
            foreach ($ppmp->signatures as $sig) {
                if ($sig->role === 'end_user') {
                    $newSig = $sig->replicate();
                    $newSig->ppmp_id = $childPpmp->id;
                    $newSig->save();
                }
            }

            $scopeLabel = match ($scope) {
                'PPMP_APP' => 'PPMP/APP Only',
                'ATTACHMENT_LIST' => 'List of Attachment Only',
                default => 'PPMP/APP & List of Attachment',
            };

            // Route log on parent
            $this->markLatestPendingRouteReceived($ppmp->id, $ppmp->admin_received_at ?? $now);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $admin->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'admin',
                'to_role' => 'end_user',
                'action' => 'ADMIN_APPROVED_' . $type,
                'status' => $ppmp->status,
                'remarks' => "{$type} ({$scopeLabel}) approved by Administrator. Created revision in DRAFT.",
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            // Initial route log on child
            PpmpRoute::create([
                'ppmp_id' => $childPpmp->id,
                'from_user_id' => $admin->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'admin',
                'to_role' => 'end_user',
                'action' => 'DRAFT_CREATED',
                'status' => 'DRAFT',
                'remarks' => "Created from approved {$type} request [Scope: {$scopeLabel}] (Previous Baseline: PPMP No. {$ppmp->ppmp_number}).",
                'submitted_at' => $now,
                'received_at' => $now,
                'acted_at' => $now,
            ]);

            // Notify Creator
            SystemNotification::notify(
                $ppmp->created_by,
                "{$type} Request Approved",
                "Your {$type} request ({$scopeLabel}) for PPMP No. {$ppmp->ppmp_number} was approved! A revision has been reopened in DRAFT for you to edit.",
                $childPpmp->id,
                'success'
            );

            AuditLog::log('PPMP_AMENDMENT_APPROVED', 'ppmps', $ppmp->id, null, [
                'parent_id' => $ppmp->id,
                'new_ppmp_id' => $childPpmp->id,
                'type' => $type,
            ], $admin->id);

            return $childPpmp;
        });

        return response()->json([
            'message' => "{$type} request approved. New PPMP No. {$newPpmp->ppmp_number} initialized in DRAFT.",
            'ppmp' => $newPpmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser', 'parent']),
        ]);
    }

    /**
     * 8c. Admin Rejects Amendment / Supplemental Request
     */
    public function rejectAmendmentRequest(Request $request, string $uuid): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Only Administrators can disapprove amendment requests.'], 403);
        }

        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        if ($ppmp->amendment_status !== 'PENDING_APPROVAL') {
            return response()->json(['message' => 'This PPMP does not have a pending amendment/supplemental request.'], 422);
        }

        $validated = $request->validate([
            'remarks' => 'required|string|min:3',
        ]);

        $now = now();
        $type = $ppmp->requested_amendment_type ?: $ppmp->amendment_type ?: 'Request';

        DB::transaction(function () use ($ppmp, $admin, $validated, $now, $type) {
            $this->markLatestPendingRouteReceived($ppmp->id, $ppmp->admin_received_at ?? $now);

            $ppmp->update([
                'amendment_status' => 'REJECTED',
            ]);

            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $admin->id,
                'to_user_id' => $ppmp->created_by,
                'from_role' => 'admin',
                'to_role' => 'end_user',
                'action' => 'ADMIN_REJECTED_' . strtoupper($type),
                'status' => $ppmp->status,
                'remarks' => "Disapproved by Administrator: {$validated['remarks']}",
                'submitted_at' => $now,
                'acted_at' => $now,
            ]);

            SystemNotification::notify(
                $ppmp->created_by,
                "{$type} Request Disapproved",
                "Your {$type} request for PPMP No. {$ppmp->ppmp_number} was disapproved by Administrator: {$validated['remarks']}",
                $ppmp->id,
                'warning'
            );

            AuditLog::log('PPMP_AMENDMENT_REJECTED', 'ppmps', $ppmp->id, null, [
                'remarks' => $validated['remarks'],
            ], $admin->id);
        });

        return response()->json([
            'message' => 'Request disapproved.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'signatures.user', 'routes.fromUser', 'routes.toUser']),
        ]);
    }

    /**
     * Apply reviewer field modifications and record change logs
     */
    private function applyFieldModifications(Ppmp $ppmp, array $fieldChanges, User $user, string $role): void
    {
        if (empty($fieldChanges)) {
            return;
        }

        foreach ($fieldChanges as $change) {
            $itemId = $change['item_id'] ?? null;
            $fieldName = $change['field_name'] ?? '';
            $oldVal = $change['old_value'] ?? '';
            $newVal = $change['new_value'] ?? '';

            if ($itemId && in_array($fieldName, ['estimated_budget', 'description', 'procurement_mode', 'quantity_size'])) {
                $item = PpmpItem::where('ppmp_id', $ppmp->id)->where('id', $itemId)->first();
                if ($item) {
                    $item->update([$fieldName => $newVal]);

                    // If budget changed, update ppmp total_budget
                    if ($fieldName === 'estimated_budget') {
                        $ppmp->update([
                            'total_budget' => $ppmp->items()->sum('estimated_budget'),
                        ]);
                    }
                }
            }

            PpmpChangeLog::create([
                'ppmp_id' => $ppmp->id,
                'user_id' => $user->id,
                'role' => $role,
                'field_name' => $fieldName,
                'old_value' => (string) $oldVal,
                'new_value' => (string) $newVal,
                'action' => 'field_correction',
            ]);
        }
    }
}
