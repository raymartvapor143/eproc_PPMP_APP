<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PpmpRoute;
use App\Models\PpmpSignatory;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Role-specific dashboard metrics and activity feed
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $metrics = [];
        $recentPpmps = [];

        if ($user->isEndUser()) {
            $base = Ppmp::where('created_by', $user->id)->whereDoesntHave('children');

            $metrics = [
                'draft' => (clone $base)->where('status', 'DRAFT')->count(),
                'head_pending' => (clone $base)->where('status', 'HEAD_PENDING')->count(),
                'head_returned' => (clone $base)->where('status', 'HEAD_RETURNED')->count(),
                'ready_for_review' => (clone $base)->where('status', 'HEAD_APPROVED')->count(),
                'in_review' => (clone $base)->whereIn('status', ['BUDGET_OFFICER_REVIEW', 'OPPMO_REVIEW', 'TWG_REVIEW'])->count(),
                'returned_by_reviewer' => (clone $base)->whereIn('status', ['BUDGET_OFFICER_RETURNED', 'OPPMO_RETURNED', 'TWG_RETURNED'])->count(),
                'ready_to_print' => (clone $base)->where('status', 'READY_TO_PRINT')->count(),
                'total' => (clone $base)->count(),
            ];

            $recentPpmps = (clone $base)->with(['office', 'signatures'])->latest('updated_at')->take(100)->get();

        } elseif ($user->isHead()) {
            $base = Ppmp::where('office_id', $user->office_id)->whereDoesntHave('children');

            $metrics = [
                'pending_endorsement' => (clone $base)->where('status', 'HEAD_PENDING')->count(),
                'endorsed_approved' => (clone $base)->whereNotIn('status', ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED'])->count(),
                'returned' => (clone $base)->where('status', 'HEAD_RETURNED')->count(),
                'total_office' => (clone $base)->count(),
            ];

            $recentPpmps = (clone $base)->with(['creator', 'signatures'])->latest('updated_at')->take(100)->get();

        } elseif ($user->isBudgetOfficer()) {
            $base = Ppmp::whereDoesntHave('children');

            $metrics = [
                'pending_review' => (clone $base)->where('status', 'BUDGET_OFFICER_REVIEW')->count(),
                'returned' => (clone $base)->where('status', 'BUDGET_OFFICER_RETURNED')->count(),
                'approved' => (clone $base)->whereNotIn('status', ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED', 'BUDGET_OFFICER_REVIEW', 'BUDGET_OFFICER_RETURNED'])->count(),
            ];

            $recentPpmps = (clone $base)->whereIn('status', ['BUDGET_OFFICER_REVIEW', 'BUDGET_OFFICER_RETURNED', 'OPPMO_REVIEW', 'TWG_REVIEW', 'READY_TO_PRINT'])
                ->with(['office', 'creator', 'signatures'])
                ->latest('updated_at')
                ->take(100)
                ->get();

        } elseif ($user->isOppmo()) {
            $base = Ppmp::whereDoesntHave('children');

            $metrics = [
                'pending_review' => (clone $base)->where('status', 'OPPMO_REVIEW')->count(),
                'returned' => (clone $base)->where('status', 'OPPMO_RETURNED')->count(),
                'approved' => (clone $base)->whereIn('status', ['TWG_REVIEW', 'READY_TO_PRINT'])->count(),
            ];

            $recentPpmps = (clone $base)->whereIn('status', ['OPPMO_REVIEW', 'OPPMO_RETURNED', 'TWG_REVIEW', 'READY_TO_PRINT'])
                ->with(['office', 'creator', 'signatures'])
                ->latest('updated_at')
                ->take(100)
                ->get();

        } elseif ($user->isTwg()) {
            $base = Ppmp::whereDoesntHave('children');

            $metrics = [
                'pending_review' => (clone $base)->where('status', 'TWG_REVIEW')->count(),
                'returned' => (clone $base)->where('status', 'TWG_RETURNED')->count(),
                'ready_to_print' => (clone $base)->where('status', 'READY_TO_PRINT')->count(),
            ];

            $recentPpmps = (clone $base)->whereIn('status', ['TWG_REVIEW', 'TWG_RETURNED', 'READY_TO_PRINT'])
                ->with(['office', 'creator', 'signatures'])
                ->latest('updated_at')
                ->take(100)
                ->get();

        } else {
            // Admin
            $base = Ppmp::whereDoesntHave('children');

            $metrics = [
                'total_ppmps' => (clone $base)->count(),
                'total_budget' => (float) ((clone $base)->sum('total_budget') ?? 0),
                'draft' => (clone $base)->where('status', 'DRAFT')->count(),
                'head_pending' => (clone $base)->where('status', 'HEAD_PENDING')->count(),
                'in_review' => (clone $base)->whereIn('status', ['BUDGET_OFFICER_REVIEW', 'OPPMO_REVIEW', 'TWG_REVIEW'])->count(),
                'ready_to_print' => (clone $base)->where('status', 'READY_TO_PRINT')->count(),
                'pending_amendments' => Ppmp::where('amendment_status', 'PENDING_APPROVAL')->count(),
            ];

            $recentPpmps = (clone $base)->with(['office', 'creator', 'signatures'])->latest('updated_at')->take(100)->get();

            // Budget distribution by office
            $budgetByOffice = (clone $base)
                ->join('offices', 'ppmps.office_id', '=', 'offices.id')
                ->selectRaw('offices.id, offices.code, offices.name, count(ppmps.id) as ppmp_count, sum(ppmps.total_budget) as total_budget')
                ->groupBy('offices.id', 'offices.code', 'offices.name')
                ->orderByDesc('total_budget')
                ->get()
                ->map(function ($row) {
                    return [
                        'office_id' => $row->id,
                        'code' => $row->code,
                        'name' => $row->name,
                        'ppmp_count' => (int) $row->ppmp_count,
                        'total_budget' => (float) $row->total_budget,
                    ];
                });

            // Procurement Mode breakdown from items
            $procurementModes = PpmpItem::whereHas('ppmp', function ($q) {
                    $q->whereDoesntHave('children');
                })
                ->selectRaw('procurement_mode, count(*) as item_count, sum(estimated_budget) as total_amount')
                ->whereNotNull('procurement_mode')
                ->where('procurement_mode', '!=', '')
                ->groupBy('procurement_mode')
                ->orderByDesc('total_amount')
                ->get()
                ->map(function ($row) {
                    return [
                        'mode' => $row->procurement_mode,
                        'item_count' => (int) $row->item_count,
                        'total_amount' => (float) $row->total_amount,
                    ];
                });

            // Status Distribution for Visual Analytics
            $statusDistribution = [
                ['status' => 'DRAFT', 'label' => 'Drafts', 'count' => $metrics['draft'], 'color' => '#64748b'],
                ['status' => 'HEAD_PENDING', 'label' => 'Pending Office Head', 'count' => $metrics['head_pending'], 'color' => '#f59e0b'],
                ['status' => 'IN_REVIEW', 'label' => 'Under Review (PBO/OPPMO/TWG)', 'count' => $metrics['in_review'], 'color' => '#6366f1'],
                ['status' => 'READY_TO_PRINT', 'label' => 'Ready to Print / Approved', 'count' => $metrics['ready_to_print'], 'color' => '#10b981'],
                ['status' => 'PENDING_AMENDMENTS', 'label' => 'Pending Amendments', 'count' => $metrics['pending_amendments'], 'color' => '#a855f7'],
            ];

            $adminAnalytics = [
                'budget_by_office' => $budgetByOffice,
                'procurement_modes' => $procurementModes,
                'status_distribution' => $statusDistribution,
            ];
        }

        // Recent Routing Actions
        $recentRoutes = PpmpRoute::with(['ppmp', 'fromUser', 'toUser'])
            ->latest('id')
            ->take(8)
            ->get();

        $signatories = $user->isAdmin() ? PpmpSignatory::latest('id')->get() : [];
        $users = $user->isAdmin() ? User::with('office')->orderBy('name')->get() : [];
        $offices = $user->isAdmin() ? Office::with('head')->orderBy('name')->get() : [];

        return response()->json([
            'role' => $user->role,
            'metrics' => $metrics,
            'recent_ppmps' => $recentPpmps,
            'recent_routes' => $recentRoutes,
            'signatories' => $signatories,
            'users' => $users,
            'offices' => $offices,
            'analytics' => $adminAnalytics ?? null,
        ]);
    }
}
