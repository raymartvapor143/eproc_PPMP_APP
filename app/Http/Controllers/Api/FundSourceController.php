<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FundSource;
use App\Models\Ppmp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundSourceController extends Controller
{
    /**
     * List fund sources.
     * End-users receive active sources only. Admins can view all with usage counts.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user && $user->isAdmin();
        $activeOnly = $request->boolean('active_only') || !$isAdmin;

        $query = FundSource::query()->ordered();

        if ($activeOnly) {
            $query->active();
        }

        $fundSources = $query->get();

        // If admin, attach PPMP usage count to each source
        if ($isAdmin) {
            // Count PPMPs per fund source name
            $usageCounts = Ppmp::select('source_of_fund', DB::raw('count(*) as count'))
                ->whereNotNull('source_of_fund')
                ->groupBy('source_of_fund')
                ->pluck('count', 'source_of_fund')
                ->toArray();

            $fundSources->transform(function ($item) use ($usageCounts) {
                $item->ppmps_count = $usageCounts[$item->name] ?? 0;
                return $item;
            });
        }

        return response()->json($fundSources);
    }

    /**
     * Create a new fund source (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255|unique:fund_sources,name',
            'code'           => 'nullable|string|max:50',
            'workflow_route' => 'required|in:budget,pacco',
            'description'    => 'nullable|string',
            'is_active'      => 'boolean',
            'display_order'  => 'integer|min:0',
        ]);

        $fundSource = FundSource::create([
            'name'           => trim($validated['name']),
            'code'           => !empty($validated['code']) ? strtoupper(trim($validated['code'])) : null,
            'workflow_route' => $validated['workflow_route'],
            'description'    => $validated['description'] ?? null,
            'is_active'      => $validated['is_active'] ?? true,
            'display_order'  => $validated['display_order'] ?? 0,
        ]);

        AuditLog::log('FUND_SOURCE_CREATED', 'fund_sources', $fundSource->id, null, $fundSource->toArray(), $user->id);

        $fundSource->ppmps_count = 0;

        return response()->json([
            'message'     => 'Source of Fund created successfully.',
            'fund_source' => $fundSource,
        ], 201);
    }

    /**
     * Update an existing fund source (Admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $fundSource = FundSource::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'required|string|max:255|unique:fund_sources,name,' . $fundSource->id,
            'code'           => 'nullable|string|max:50',
            'workflow_route' => 'required|in:budget,pacco',
            'description'    => 'nullable|string',
            'is_active'      => 'boolean',
            'display_order'  => 'integer|min:0',
        ]);

        $old = $fundSource->toArray();
        $oldName = $fundSource->name;
        $newName = trim($validated['name']);

        $fundSource->update([
            'name'           => $newName,
            'code'           => !empty($validated['code']) ? strtoupper(trim($validated['code'])) : null,
            'workflow_route' => $validated['workflow_route'],
            'description'    => $validated['description'] ?? null,
            'is_active'      => $validated['is_active'] ?? $fundSource->is_active,
            'display_order'  => $validated['display_order'] ?? $fundSource->display_order,
        ]);

        // If the name changed, synchronize existing PPMPs that reference the old name
        if ($oldName !== $newName) {
            Ppmp::where('source_of_fund', $oldName)->update(['source_of_fund' => $newName]);
        }

        AuditLog::log('FUND_SOURCE_UPDATED', 'fund_sources', $fundSource->id, $old, $fundSource->toArray(), $user->id);

        $fundSource->ppmps_count = Ppmp::where('source_of_fund', $newName)->count();

        return response()->json([
            'message'     => 'Source of Fund updated successfully.',
            'fund_source' => $fundSource,
        ]);
    }

    /**
     * Delete a fund source (Admin only).
     * Prevents deletion if existing PPMPs reference it.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $fundSource = FundSource::findOrFail($id);

        $ppmpsCount = Ppmp::where('source_of_fund', $fundSource->name)->count();

        if ($ppmpsCount > 0) {
            return response()->json([
                'message' => "Cannot delete '{$fundSource->name}' because {$ppmpsCount} PPMP(s) are currently referencing it. You can deactivate it instead to hide it from new PPMP selections.",
            ], 422);
        }

        $old = $fundSource->toArray();
        $fundSource->delete();

        AuditLog::log('FUND_SOURCE_DELETED', 'fund_sources', $id, $old, null, $user->id);

        return response()->json([
            'message' => 'Source of Fund deleted successfully.',
        ]);
    }
}
