<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PpmpRoute;
use App\Models\PpmpSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PpmpController extends Controller
{
    /**
     * List PPMPs with server-side filtering, search, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only show active/latest PPMPs in the list (superseded PPMPs that have child PPMPs are folded inside the new PPMP)
        $query = Ppmp::with(['office', 'creator', 'items', 'signatures'])
            ->whereDoesntHave('children');

        // Scope based on role
        if ($user->isEndUser()) {
            // End users can see PPMPs created by themselves or in their office
            $query->where('created_by', $user->id);
        } elseif ($user->isHead()) {
            // Head sees PPMPs under their office
            $query->where('office_id', $user->office_id);
        } elseif ($user->isBudgetOfficer()) {
            // Budget officer sees PPMPs that reached review or beyond
            $query->whereNotIn('status', ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED']);
        } elseif ($user->isOppmo()) {
            // OPPMO sees PPMPs past budget review or historical
            $query->whereNotIn('status', ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED', 'BUDGET_OFFICER_REVIEW', 'BUDGET_OFFICER_RETURNED']);
        } elseif ($user->isTwg()) {
            // TWG sees PPMPs reaching TWG review or ready to print
            $query->whereNotIn('status', ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED', 'BUDGET_OFFICER_REVIEW', 'BUDGET_OFFICER_RETURNED', 'OPPMO_REVIEW', 'OPPMO_RETURNED']);
        }

        // Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('ppmp_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', $request->input('fiscal_year'));
        }

        if ($request->filled('office_id')) {
            $query->where('office_id', $request->input('office_id'));
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $ppmps = $query->latest('id')->paginate($perPage);

        return response()->json($ppmps);
    }

    /**
     * Create a new PPMP (End User only)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isEndUser() && !$user->isAdmin()) {
            return response()->json(['message' => 'Only End Users can initiate a new PPMP.'], 403);
        }

        $validated = $request->validate([
            'ppmp_number' => 'nullable|string|max:50',
            'title' => 'required|string|max:500',
            'account_code' => 'nullable|string',
            'fiscal_year' => 'required|string|max:10',
            'plan_type' => 'required|in:INDICATIVE,FINAL',
            'office_id' => 'nullable|exists:offices,id',
            'implementing_unit' => 'nullable|string|max:255',
            'delivery_period' => 'nullable|string|max:255',
            'place_of_delivery' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'warranty_and_other_terms' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.project_type' => 'nullable|string',
            'items.*.quantity_size' => 'nullable|string',
            'items.*.procurement_mode' => 'nullable|string',
            'items.*.pre_proc_conference' => 'nullable|boolean',
            'items.*.start_date' => 'nullable|string',
            'items.*.end_date' => 'nullable|string',
            'items.*.delivery_period' => 'nullable|string',
            'items.*.source_of_fund' => 'nullable|string',
            'items.*.estimated_budget' => 'required|numeric|min:0',
            'items.*.supporting_docs_text' => 'nullable|string',
            'items.*.remarks' => 'nullable|string',
        ]);

        $officeId = $user->office_id ?? $validated['office_id'] ?? Office::first()->id;

        $ppmp = DB::transaction(function () use ($validated, $user, $officeId) {
            // Generate unique Tracker Number: PPMP-YYYY-XXXXXX
            $year = $validated['fiscal_year'];
            $latestInYear = Ppmp::where('fiscal_year', $year)->lockForUpdate()->count();
            $nextSeq = str_pad($latestInYear + 1, 6, '0', STR_PAD_LEFT);
            $trackingNumber = "PPMP-{$year}-{$nextSeq}";

            // User input for PPMP Number (e.g. 0, 1, 2, 3...) or fallback to sequential index
            $ppmpNumber = isset($validated['ppmp_number']) && $validated['ppmp_number'] !== ''
                ? (string) $validated['ppmp_number']
                : (string) $latestInYear;

            $ppmp = Ppmp::create([
                'uuid' => (string) Str::uuid(),
                'tracking_number' => $trackingNumber,
                'ppmp_number' => $ppmpNumber,
                'office_id' => $officeId,
                'implementing_unit' => $validated['implementing_unit'] ?? null,
                'created_by' => $user->id,
                'title' => $validated['title'],
                'account_code' => $validated['account_code'] ?? null,
                'fiscal_year' => $validated['fiscal_year'],
                'plan_type' => $validated['plan_type'],
                'is_annual' => true,
                'delivery_period' => $validated['delivery_period'] ?? 'As scheduled',
                'place_of_delivery' => $validated['place_of_delivery'] ?? 'Capitol Complex, Digos City',
                'payment_method' => $validated['payment_method'] ?? 'LDDAP-ADA / Government Check',
                'warranty_and_other_terms' => $validated['warranty_and_other_terms'] ?? null,
                'total_budget' => 0.00,
                'status' => 'DRAFT',
                'prepared_at' => now(),
            ]);

            $total = 0;
            foreach ($validated['items'] as $index => $itemData) {
                $itemBudget = (float) $itemData['estimated_budget'];
                $total += $itemBudget;

                PpmpItem::create([
                    'ppmp_id' => $ppmp->id,
                    'item_no' => $index + 1,
                    'description' => $itemData['description'],
                    'project_type' => $itemData['project_type'] ?? 'Goods',
                    'quantity_size' => $itemData['quantity_size'] ?? '',
                    'procurement_mode' => $itemData['procurement_mode'] ?? 'Public Bidding',
                    'pre_proc_conference' => (bool) ($itemData['pre_proc_conference'] ?? false),
                    'start_date' => $itemData['start_date'] ?? null,
                    'end_date' => $itemData['end_date'] ?? null,
                    'delivery_period' => $itemData['delivery_period'] ?? null,
                    'source_of_fund' => $itemData['source_of_fund'] ?? 'General Fund',
                    'estimated_budget' => $itemBudget,
                    'supporting_docs_text' => $itemData['supporting_docs_text'] ?? null,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }

            $ppmp->update(['total_budget' => $total]);

            // Create End User "Prepared By" e-signature indicator
            PpmpSignature::create([
                'ppmp_id' => $ppmp->id,
                'user_id' => $user->id,
                'role' => 'end_user',
                'signature_type' => 'full_esignature',
                'signature_indicator' => 'ESIG-PREPARED-' . strtoupper(substr(md5($user->id . $ppmp->id . now()), 0, 8)),
                'signer_name' => $user->name,
                'signer_designation' => $user->designation ?? 'Implementing Unit Staff',
                'signed_at' => now(),
            ]);

            // Initial route record
            PpmpRoute::create([
                'ppmp_id' => $ppmp->id,
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
                'from_role' => 'end_user',
                'to_role' => 'end_user',
                'action' => 'DRAFT_CREATED',
                'status' => 'DRAFT',
                'remarks' => 'PPMP Draft created and initialized.',
                'submitted_at' => now(),
                'received_at' => now(),
                'acted_at' => now(),
            ]);

            AuditLog::log('PPMP_CREATED', 'ppmps', $ppmp->id, null, ['number' => $ppmp->ppmp_number, 'total' => $total], $user->id);

            return $ppmp;
        });

        return response()->json([
            'message' => 'PPMP created successfully in DRAFT status.',
            'ppmp' => $ppmp->load(['office', 'creator', 'items', 'signatures', 'attachments', 'routes']),
        ], 201);
    }

    /**
     * Get single PPMP details with full related information
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $ppmp = Ppmp::with([
            'office.head',
            'creator',
            'items',
            'attachments.uploader',
            'reviews.reviewer',
            'signatures.user',
            'routes.fromUser',
            'routes.toUser',
            'changeLogs.user',
            'parent.items',
            'parent.signatures.user',
            'parent.office',
            'children.items',
        ])->where('uuid', $uuid)->firstOrFail();

        // Enforce strict access control against IDOR / unauthorized viewing
        if (!$user->isAdmin()) {
            if ($user->isEndUser() && $ppmp->created_by !== $user->id && $ppmp->office_id !== $user->office_id) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to view this PPMP.'], 403);
            }
            if ($user->isHead() && $ppmp->office_id !== $user->office_id) {
                return response()->json(['message' => 'Unauthorized. You may only view PPMPs from your assigned office.'], 403);
            }
            if ($user->isBudgetOfficer() && in_array($ppmp->status, ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED'])) {
                return response()->json(['message' => 'PPMP is not yet submitted for Budget review.'], 403);
            }
            if ($user->isOppmo() && in_array($ppmp->status, ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED', 'BUDGET_OFFICER_REVIEW', 'BUDGET_OFFICER_RETURNED'])) {
                return response()->json(['message' => 'PPMP is not yet submitted for OPPMO review.'], 403);
            }
            if ($user->isTwg() && in_array($ppmp->status, ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED', 'BUDGET_OFFICER_REVIEW', 'BUDGET_OFFICER_RETURNED', 'OPPMO_REVIEW', 'OPPMO_RETURNED'])) {
                return response()->json(['message' => 'PPMP is not yet submitted for BAC-TWG review.'], 403);
            }
        }

        // Recursively build full ancestor history from immediate parent down to root annual PPMP
        // Guarded with cycle detection set and maximum iteration limit to prevent infinite loops
        $history = [];
        $currentParent = $ppmp->parent;
        $visitedIds = [$ppmp->id];
        $maxDepth = 25;
        $depth = 0;

        while ($currentParent && $depth < $maxDepth) {
            if (in_array($currentParent->id, $visitedIds, true)) {
                break; // Cycle detected, terminate loop immediately
            }
            $visitedIds[] = $currentParent->id;
            $depth++;

            $currentParent->loadMissing([
                'items',
                'signatures.user',
                'office',
                'creator',
                'attachments.uploader',
                'routes.fromUser',
                'routes.toUser',
                'reviews.reviewer',
                'changeLogs.user',
            ]);
            $history[] = $currentParent;
            $currentParent = $currentParent->parent;
        }
        $ppmp->setRelation('history', collect($history));

        // Signatories for report / details
        $activeBudget = \App\Models\PpmpSignatory::budgetRequirement()->active()->first();
        $activeBac = \App\Models\PpmpSignatory::bacSecretariat()->active()->first();
        $activeGovernor = \App\Models\PpmpSignatory::approvedBy()->active()->first();

        return response()->json([
            'ppmp' => $ppmp,
            'history' => $history,
            'default_signatories' => [
                'budget_requirement' => $activeBudget ? [
                    'name' => $activeBudget->name,
                    'position' => $activeBudget->position,
                ] : null,
                'bac_secretariat' => $activeBac ? [
                    'name' => $activeBac->name,
                    'position' => $activeBac->position,
                ] : null,
                'approved_by' => $activeGovernor ? [
                    'name' => $activeGovernor->name,
                    'position' => $activeGovernor->position,
                ] : null,
            ],
        ]);
    }

    /**
     * Update an editable PPMP (End User when DRAFT or RETURNED)
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        // Check edit permission: Creator when in draft/head approved/returned, OR active reviewer in their stage, OR admin
        $isCreatorEditable = ($ppmp->created_by === $user->id) && in_array($ppmp->status, [
            'DRAFT',
            'HEAD_PENDING',
            'HEAD_APPROVED',
            'HEAD_RETURNED',
            'BUDGET_OFFICER_RETURNED',
            'OPPMO_RETURNED',
            'TWG_RETURNED'
        ]);

        $isReviewerEditable = (
            ($user->isHead() && in_array($ppmp->status, ['HEAD_PENDING', 'HEAD_APPROVED']) && $user->office_id === $ppmp->office_id) ||
            ($user->isBudgetOfficer() && $ppmp->status === 'BUDGET_OFFICER_REVIEW') ||
            ($user->isOppmo() && $ppmp->status === 'OPPMO_REVIEW') ||
            ($user->isTwg() && $ppmp->status === 'TWG_REVIEW')
        );

        if (!$isCreatorEditable && !$isReviewerEditable && !$user->isAdmin()) {
            return response()->json([
                'message' => "PPMP cannot be modified by you in its current status: {$ppmp->status}."
            ], 403);
        }

        // Enforce Amendment Scope restriction: if scope was ATTACHMENT_LIST only, PPMP/APP items are locked
        if ($ppmp->amendment_scope === 'ATTACHMENT_LIST' && !$user->isAdmin()) {
            return response()->json([
                'message' => "PPMP items and plan details cannot be modified for this revision. The approved request scope is restricted to the PPMP List of Attachment only."
            ], 403);
        }

        $validated = $request->validate([
            'ppmp_number' => 'nullable|string|max:50',
            'title' => 'required|string|max:500',
            'account_code' => 'nullable|string',
            'fiscal_year' => 'required|string|max:10',
            'plan_type' => 'required|in:INDICATIVE,FINAL',
            'implementing_unit' => 'nullable|string|max:255',
            'delivery_period' => 'nullable|string|max:255',
            'place_of_delivery' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'warranty_and_other_terms' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.description' => 'required|string',
            'items.*.project_type' => 'nullable|string',
            'items.*.quantity_size' => 'nullable|string',
            'items.*.procurement_mode' => 'nullable|string',
            'items.*.pre_proc_conference' => 'nullable|boolean',
            'items.*.start_date' => 'nullable|string',
            'items.*.end_date' => 'nullable|string',
            'items.*.delivery_period' => 'nullable|string',
            'items.*.source_of_fund' => 'nullable|string',
            'items.*.estimated_budget' => 'required|numeric|min:0',
            'items.*.supporting_docs_text' => 'nullable|string',
            'items.*.remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($ppmp, $validated, $user) {
            $oldValues = $ppmp->only(['title', 'plan_type', 'total_budget']);

            $ppmp->update([
                'ppmp_number' => isset($validated['ppmp_number']) && $validated['ppmp_number'] !== '' ? $validated['ppmp_number'] : $ppmp->ppmp_number,
                'implementing_unit' => array_key_exists('implementing_unit', $validated) ? $validated['implementing_unit'] : $ppmp->implementing_unit,
                'title' => $validated['title'],
                'account_code' => $validated['account_code'] ?? $ppmp->account_code,
                'fiscal_year' => $validated['fiscal_year'],
                'plan_type' => $validated['plan_type'],
                'delivery_period' => $validated['delivery_period'] ?? $ppmp->delivery_period,
                'place_of_delivery' => $validated['place_of_delivery'] ?? $ppmp->place_of_delivery,
                'payment_method' => $validated['payment_method'] ?? $ppmp->payment_method,
                'warranty_and_other_terms' => array_key_exists('warranty_and_other_terms', $validated) ? $validated['warranty_and_other_terms'] : $ppmp->warranty_and_other_terms,
            ]);

            // Replace items safely
            $ppmp->items()->delete();

            $total = 0;
            foreach ($validated['items'] as $index => $itemData) {
                $itemBudget = (float) $itemData['estimated_budget'];
                $total += $itemBudget;

                PpmpItem::create([
                    'ppmp_id' => $ppmp->id,
                    'item_no' => $index + 1,
                    'description' => $itemData['description'],
                    'project_type' => $itemData['project_type'] ?? 'Goods',
                    'quantity_size' => $itemData['quantity_size'] ?? '',
                    'procurement_mode' => $itemData['procurement_mode'] ?? 'Public Bidding',
                    'pre_proc_conference' => (bool) ($itemData['pre_proc_conference'] ?? false),
                    'start_date' => $itemData['start_date'] ?? null,
                    'end_date' => $itemData['end_date'] ?? null,
                    'delivery_period' => $itemData['delivery_period'] ?? null,
                    'source_of_fund' => $itemData['source_of_fund'] ?? 'General Fund',
                    'estimated_budget' => $itemBudget,
                    'supporting_docs_text' => $itemData['supporting_docs_text'] ?? null,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }

            $ppmp->update(['total_budget' => $total]);

            // If a reviewer edited the PPMP, log the field-level change
            if (!$user->isEndUser()) {
                \App\Models\PpmpChangeLog::create([
                    'ppmp_id' => $ppmp->id,
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'field_name' => 'items_and_specifications',
                    'old_value' => 'Old Total: ' . ($oldValues['total_budget'] ?? 0),
                    'new_value' => 'New Total: ' . $total,
                    'action' => 'reviewer_edit',
                ]);
            }

            AuditLog::log('PPMP_UPDATED', 'ppmps', $ppmp->id, $oldValues, ['total_budget' => $total], $user->id);
        });

        return response()->json([
            'message' => 'PPMP updated successfully.',
            'ppmp' => $ppmp->fresh(['office', 'creator', 'items', 'signatures', 'attachments', 'routes']),
        ]);
    }

    /**
     * Store customized PPMP List of Attachment data
     */
    public function saveAttachmentList(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        // Check edit permission: Creator when in draft/head approved/returned, OR active reviewer in their stage, OR admin
        $isCreatorEditable = ($ppmp->created_by === $user->id) && in_array($ppmp->status, [
            'DRAFT',
            'HEAD_PENDING',
            'HEAD_APPROVED',
            'HEAD_RETURNED',
            'BUDGET_OFFICER_RETURNED',
            'OPPMO_RETURNED',
            'TWG_RETURNED'
        ]);

        $isReviewerEditable = (
            ($user->isHead() && in_array($ppmp->status, ['HEAD_PENDING', 'HEAD_APPROVED']) && $user->office_id === $ppmp->office_id) ||
            ($user->isBudgetOfficer() && $ppmp->status === 'BUDGET_OFFICER_REVIEW') ||
            ($user->isOppmo() && $ppmp->status === 'OPPMO_REVIEW') ||
            ($user->isTwg() && $ppmp->status === 'TWG_REVIEW')
        );

        if (!$isCreatorEditable && !$isReviewerEditable && !$user->isAdmin()) {
            return response()->json([
                'message' => "PPMP List of Attachment is locked. Once submitted for review, only the authorized reviewer can modify it."
            ], 403);
        }

        // Enforce Amendment Scope restriction: if scope was PPMP_APP only, List of Attachment is locked
        if ($ppmp->amendment_scope === 'PPMP_APP' && !$user->isAdmin()) {
            return response()->json([
                'message' => "PPMP List of Attachment cannot be modified for this revision. The approved request scope is restricted to PPMP/APP procurement items only."
            ], 403);
        }

        $validated = $request->validate([
            'office_name' => 'nullable|string',
            'project_title' => 'nullable|string',
            'total_budget' => 'nullable|numeric',
            'charges' => 'nullable|string',
            'place_of_delivery' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'delivery_period' => 'nullable|string',
            'prepared_by_name' => 'nullable|string',
            'prepared_by_position' => 'nullable|string',
            'submitted_by_name' => 'nullable|string',
            'submitted_by_position' => 'nullable|string',
            'approved_by_name' => 'nullable|string',
            'approved_by_position' => 'nullable|string',
            'rows' => 'required|array',
        ]);

        $ppmp->update([
            'attachment_list_data' => $validated,
        ]);

        AuditLog::log(
            'PPMP_ATTACHMENT_LIST_GENERATED',
            'ppmps',
            $ppmp->id,
            null,
            ['items_count' => count($validated['rows'])],
            $user->id
        );

        return response()->json([
            'message' => 'PPMP List of Attachment saved successfully.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'items', 'signatures.user', 'attachments', 'routes']),
        ]);
    }

    /**
     * Save / Generate APP (Annual Procurement Plan) data for PPMP
     */
    public function saveAppData(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        $isCreatorEditable = ($ppmp->created_by === $user->id) && in_array($ppmp->status, [
            'DRAFT',
            'HEAD_PENDING',
            'HEAD_APPROVED',
            'HEAD_RETURNED',
            'BUDGET_OFFICER_RETURNED',
            'OPPMO_RETURNED',
            'TWG_RETURNED'
        ]);

        $isReviewerEditable = (
            ($user->isHead() && in_array($ppmp->status, ['HEAD_PENDING', 'HEAD_APPROVED']) && $user->office_id === $ppmp->office_id) ||
            ($user->isBudgetOfficer() && $ppmp->status === 'BUDGET_OFFICER_REVIEW') ||
            ($user->isOppmo() && $ppmp->status === 'OPPMO_REVIEW') ||
            ($user->isTwg() && $ppmp->status === 'TWG_REVIEW')
        );

        if (!$isCreatorEditable && !$isReviewerEditable && !$user->isAdmin()) {
            return response()->json([
                'message' => "Annual Procurement Plan (APP) is locked. Once submitted for review, only the authorized reviewer can modify it."
            ], 403);
        }

        $validated = $request->validate([
            'fiscal_year' => 'nullable|string',
            'plan_version_type' => 'nullable|string', // INDICATIVE, FINAL, UPDATED
            'updated_version_no' => 'nullable|string',
            'prepared_by_name' => 'nullable|string',
            'prepared_by_position' => 'nullable|string',
            'recommending_name' => 'nullable|string',
            'recommending_position' => 'nullable|string',
            'approved_by_name' => 'nullable|string',
            'approved_by_position' => 'nullable|string',
            'rows' => 'required|array',
        ]);

        $ppmp->update([
            'app_data' => $validated,
        ]);

        AuditLog::log(
            'PPMP_APP_DATA_SAVED',
            'ppmps',
            $ppmp->id,
            null,
            ['items_count' => count($validated['rows'])],
            $user->id
        );

        return response()->json([
            'message' => 'Annual Procurement Plan (APP) data saved successfully.',
            'ppmp' => $ppmp->fresh(['office.head', 'creator', 'items', 'signatures.user', 'attachments', 'routes']),
        ]);
    }
}
