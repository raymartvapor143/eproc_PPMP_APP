import React, { useState } from 'react';
import {
    BarChart3,
    TrendingUp,
    PieChart,
    Building2,
    CheckCircle2,
    Clock,
    AlertTriangle,
    Layers,
    DollarSign,
    RefreshCw,
    Download,
    Eye,
} from 'lucide-react';
import { formatCurrency } from '../UI/StatusBadge';

export const AdminVisualAnalytics = ({ analytics, metrics, recentPpmps, onSelectPpmp }) => {
    const [filterFiscalYear, setFilterFiscalYear] = useState('ALL');

    const totalBudget = metrics?.total_budget || 0;
    const totalPpmps = metrics?.total_ppmps || 0;
    const readyToPrint = metrics?.ready_to_print || 0;
    const inReview = metrics?.in_review || 0;
    const draft = metrics?.draft || 0;
    const headPending = metrics?.head_pending || 0;
    const pendingAmendments = metrics?.pending_amendments || 0;

    const budgetByOffice = analytics?.budget_by_office || [];
    const procurementModes = analytics?.procurement_modes || [];
    const statusDistribution = analytics?.status_distribution || [
        { status: 'DRAFT', label: 'Drafts', count: draft, color: '#64748b' },
        { status: 'HEAD_PENDING', label: 'Pending Office Head', count: headPending, color: '#f59e0b' },
        { status: 'IN_REVIEW', label: 'Under Review', count: inReview, color: '#6366f1' },
        { status: 'READY_TO_PRINT', label: 'Approved / Ready to Print', count: readyToPrint, color: '#10b981' },
        { status: 'PENDING_AMENDMENTS', label: 'Pending Amendments', count: pendingAmendments, color: '#a855f7' },
    ];

    // Find highest budget office for scaling progress bars
    const maxOfficeBudget = Math.max(...budgetByOffice.map((o) => o.total_budget), 1);
    // Find highest procurement mode amount for scaling
    const maxProcurementBudget = Math.max(...procurementModes.map((p) => p.total_amount), 1);

    // Approval completion rate
    const completionRate = totalPpmps > 0 ? Math.round((readyToPrint / totalPpmps) * 100) : 0;
    const inReviewRate = totalPpmps > 0 ? Math.round((inReview / totalPpmps) * 100) : 0;

    return (
        <div className="space-y-6">
            {/* Header / Summary banner */}
            <div className="bg-gradient-to-r from-slate-900 via-blue-950 to-indigo-950 text-white p-6 rounded-2xl shadow-md border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-blue-500/20 text-blue-300 border border-blue-400/30 text-xs font-semibold uppercase tracking-wider mb-2">
                        <BarChart3 className="w-3.5 h-3.5" />
                        Executive Procurement Intelligence
                    </div>
                    <h2 className="text-xl sm:text-2xl font-black tracking-tight">
                        Visual Procurement Analytics & Insights
                    </h2>
                    <p className="text-xs text-slate-300 mt-1 max-w-2xl">
                        Comprehensive visual metrics of provincial annual procurement plans, office expenditure allocations, procurement modalities, and workflow progression velocity.
                    </p>
                </div>

                <div className="flex items-center gap-3 bg-white/10 backdrop-blur-md px-4 py-3 rounded-xl border border-white/10 shrink-0">
                    <div>
                        <div className="text-[10px] font-bold text-blue-200 uppercase tracking-wider">
                            Total Approved & Active Budget
                        </div>
                        <div className="text-xl sm:text-2xl font-black font-mono text-emerald-400">
                            {formatCurrency(totalBudget)}
                        </div>
                    </div>
                </div>
            </div>

            {/* KPI Cards Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {/* Card 1: Total Plans & Completion */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-blue-300 transition group">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Total Registered PPMPs
                        </span>
                        <div className="p-2.5 bg-blue-50 text-blue-600 rounded-lg group-hover:bg-blue-600 group-hover:text-white transition">
                            <Layers className="w-5 h-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-2xl sm:text-3xl font-black text-slate-900">{totalPpmps}</span>
                        <span className="text-xs font-semibold text-emerald-600">Active Plans</span>
                    </div>
                    <div className="mt-3">
                        <div className="flex justify-between text-[11px] font-medium text-slate-600 mb-1">
                            <span>Ready to Print</span>
                            <span className="font-bold text-emerald-600">{completionRate}% completed</span>
                        </div>
                        <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-emerald-500 rounded-full transition-all duration-500"
                                style={{ width: `${completionRate}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* Card 2: In Active Review Pipeline */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-indigo-300 transition group">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Review Pipeline
                        </span>
                        <div className="p-2.5 bg-indigo-50 text-indigo-600 rounded-lg group-hover:bg-indigo-600 group-hover:text-white transition">
                            <Clock className="w-5 h-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-2xl sm:text-3xl font-black text-indigo-900">{inReview}</span>
                        <span className="text-xs font-semibold text-indigo-600">in PBO / OPPMO / TWG</span>
                    </div>
                    <div className="mt-3">
                        <div className="flex justify-between text-[11px] font-medium text-slate-600 mb-1">
                            <span>Review Volume</span>
                            <span className="font-bold text-indigo-600">{inReviewRate}% of total</span>
                        </div>
                        <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-indigo-500 rounded-full transition-all duration-500"
                                style={{ width: `${inReviewRate}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* Card 3: Ready to Print / Cleared */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition group">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Fully Cleared (Ready to Print)
                        </span>
                        <div className="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg group-hover:bg-emerald-600 group-hover:text-white transition">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-2xl sm:text-3xl font-black text-emerald-900">{readyToPrint}</span>
                        <span className="text-xs font-semibold text-emerald-600">Plans Approved</span>
                    </div>
                    <div className="mt-3 text-[11px] text-slate-500 flex items-center gap-1.5">
                        <span className="w-2 h-2 rounded-full bg-emerald-500" />
                        Eligible for official APP consolidation & export
                    </div>
                </div>

                {/* Card 4: Amendments / Action Needed */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition group">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Pending Amendments
                        </span>
                        <div className="p-2.5 bg-purple-50 text-purple-600 rounded-lg group-hover:bg-purple-600 group-hover:text-white transition">
                            <RefreshCw className="w-5 h-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-2xl sm:text-3xl font-black text-purple-900">{pendingAmendments}</span>
                        <span className="text-xs font-semibold text-purple-600">Requires Head Review</span>
                    </div>
                    <div className="mt-3 text-[11px] text-slate-500 flex items-center gap-1.5">
                        <span className="w-2 h-2 rounded-full bg-purple-500" />
                        Formal requests for scope / line item alteration
                    </div>
                </div>
            </div>

            {/* Charts Row: Office Budget Breakdown & Status Funnel */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Office Budget Ranking */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wide flex items-center gap-2">
                                <Building2 className="w-4 h-4 text-blue-600" />
                                Budget Allocation by Implementing Office
                            </h3>
                            <span className="text-xs text-slate-500">
                                Ranked by total estimated budget from procurement plans
                            </span>
                        </div>
                        <span className="text-xs font-mono font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded">
                            {budgetByOffice.length} Offices Active
                        </span>
                    </div>

                    <div className="mt-4 space-y-4 max-h-96 overflow-y-auto pr-1">
                        {budgetByOffice.length === 0 ? (
                            <div className="p-8 text-center text-slate-400 text-xs italic">
                                No PPMP budget records recorded yet.
                            </div>
                        ) : (
                            budgetByOffice.map((off, idx) => {
                                const percentage = totalBudget > 0 ? Math.round((off.total_budget / totalBudget) * 100) : 0;
                                const barWidth = Math.max(Math.round((off.total_budget / maxOfficeBudget) * 100), 4);
                                return (
                                    <div key={off.office_id || idx} className="space-y-1.5 group">
                                        <div className="flex items-center justify-between text-xs">
                                            <div className="flex items-center gap-2 min-w-0 pr-2">
                                                <span className="w-5 h-5 rounded-full bg-slate-100 text-slate-600 font-mono text-[10px] font-bold flex items-center justify-center shrink-0">
                                                    {idx + 1}
                                                </span>
                                                <span className="font-mono font-bold text-blue-700 shrink-0">
                                                    {off.code}
                                                </span>
                                                <span className="text-slate-800 font-medium truncate" title={off.name}>
                                                    {off.name}
                                                </span>
                                                <span className="text-[10px] text-slate-400 shrink-0">
                                                    ({off.ppmp_count} {off.ppmp_count === 1 ? 'plan' : 'plans'})
                                                </span>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <span className="font-mono font-bold text-slate-900">
                                                    {formatCurrency(off.total_budget)}
                                                </span>
                                                <span className="text-[11px] text-slate-400 ml-1 font-semibold">
                                                    ({percentage}%)
                                                </span>
                                            </div>
                                        </div>
                                        {/* Visual Progress Bar */}
                                        <div className="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div
                                                className="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500 group-hover:from-blue-600 group-hover:to-indigo-700"
                                                style={{ width: `${barWidth}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>

                {/* Status Progression Distribution */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wide flex items-center gap-2">
                                <PieChart className="w-4 h-4 text-indigo-600" />
                                PPMP Lifecycle & Status Breakdown
                            </h3>
                            <span className="text-xs text-slate-500">
                                Distribution of plans across submission, review, and approval phases
                            </span>
                        </div>
                        <span className="text-xs font-mono font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded">
                            {totalPpmps} Total
                        </span>
                    </div>

                    <div className="mt-4 space-y-4">
                        {statusDistribution.map((item) => {
                            const percent = totalPpmps > 0 ? Math.round((item.count / totalPpmps) * 100) : 0;
                            return (
                                <div key={item.status} className="space-y-1.5">
                                    <div className="flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="w-2.5 h-2.5 rounded-full"
                                                style={{ backgroundColor: item.color }}
                                            />
                                            <span className="font-semibold text-slate-800">{item.label}</span>
                                        </div>
                                        <div className="text-right">
                                            <span className="font-bold text-slate-900">{item.count}</span>
                                            <span className="text-slate-400 text-[11px] ml-1.5 font-mono">
                                                ({percent}%)
                                            </span>
                                        </div>
                                    </div>
                                    <div className="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                        <div
                                            className="h-full rounded-full transition-all duration-500"
                                            style={{
                                                width: `${percent}%`,
                                                backgroundColor: item.color,
                                            }}
                                        />
                                    </div>
                                </div>
                            );
                        })}

                        {/* Summary Box */}
                        <div className="mt-6 p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                            <div className="text-xs text-slate-600">
                                <span className="font-bold text-slate-800">Overall Clearance Rate: </span>
                                {completionRate}% of all plans have received final readiness certification.
                            </div>
                            <span className="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">
                                {readyToPrint} Cleared
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Bottom Row: Procurement Modes & Modalities Breakdown */}
            <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
                <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wide flex items-center gap-2">
                            <TrendingUp className="w-4 h-4 text-emerald-600" />
                            Procurement Modalities Breakdown (RA 12009 / RA 9184)
                        </h3>
                        <span className="text-xs text-slate-500">
                            Line item volume and budgetary distribution grouped by procurement method
                        </span>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto">
                    {procurementModes.length === 0 ? (
                        <div className="p-8 text-center text-slate-400 text-xs italic">
                            No procurement items recorded with assigned procurement modes.
                        </div>
                    ) : (
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-700 uppercase font-semibold border-b border-slate-200">
                                <tr>
                                    <th className="p-3">Procurement Modality</th>
                                    <th className="p-3 text-center">Item Count</th>
                                    <th className="p-3">Visual Distribution</th>
                                    <th className="p-3 text-right">Total Estimated Value</th>
                                    <th className="p-3 text-right">% of Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {procurementModes.map((item, idx) => {
                                    const percent = totalBudget > 0 ? ((item.total_amount / totalBudget) * 100).toFixed(1) : '0.0';
                                    const barWidth = Math.max(Math.round((item.total_amount / maxProcurementBudget) * 100), 5);
                                    return (
                                        <tr key={idx} className="hover:bg-slate-50 transition">
                                            <td className="p-3 font-semibold text-slate-900">
                                                <div className="flex items-center gap-2">
                                                    <span className="w-2 h-2 rounded-full bg-blue-600" />
                                                    {item.mode}
                                                </div>
                                            </td>
                                            <td className="p-3 text-center font-mono font-bold text-slate-700">
                                                {item.item_count} items
                                            </td>
                                            <td className="p-3 w-48">
                                                <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                                    <div
                                                        className="h-full bg-emerald-500 rounded-full"
                                                        style={{ width: `${barWidth}%` }}
                                                    />
                                                </div>
                                            </td>
                                            <td className="p-3 text-right font-mono font-bold text-slate-900">
                                                {formatCurrency(item.total_amount)}
                                            </td>
                                            <td className="p-3 text-right font-mono font-bold text-blue-600">
                                                {percent}%
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div>
    );
};
