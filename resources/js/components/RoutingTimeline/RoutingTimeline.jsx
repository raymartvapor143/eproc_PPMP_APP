import React, { useState } from 'react';
import { formatDate } from '../UI/StatusBadge';
import { Clock, ExternalLink, X, ChevronRight, History, Printer } from 'lucide-react';

export const RoutingTimeline = ({
    routes = [],
    reviews = [],
    changeLogs = [],
    ppmp = null,
    historicalPpmps = [],
    onPrint = null,
    isOpen: controlledIsOpen = null,
    onClose = null,
    initialPpmp = null,
}) => {
    const [uncontrolledIsOpen, setUncontrolledIsOpen] = useState(false);
    const isOpen = controlledIsOpen !== null ? controlledIsOpen : uncontrolledIsOpen;
    const setIsOpen = (val) => {
        if (controlledIsOpen !== null) {
            if (!val && onClose) onClose();
        } else {
            setUncontrolledIsOpen(val);
        }
    };

    // Available PPMPs to select from: active ppmp + all historical baseline revisions
    const allPpmps = React.useMemo(() => {
        const list = [];
        if (ppmp) list.push(ppmp);
        if (Array.isArray(historicalPpmps)) {
            historicalPpmps.forEach(h => {
                if (h && !list.some(p => p.id === h.id)) {
                    list.push(h);
                }
            });
        }
        return list;
    }, [ppmp, historicalPpmps]);

    const [selectedPpmpId, setSelectedPpmpId] = useState(initialPpmp?.id || ppmp?.id);

    // Sync selected PPMP if initialPpmp changes
    React.useEffect(() => {
        if (initialPpmp?.id) {
            setSelectedPpmpId(initialPpmp.id);
        } else if (ppmp?.id && !selectedPpmpId) {
            setSelectedPpmpId(ppmp.id);
        }
    }, [initialPpmp, ppmp]);

    const activeSelectedPpmp = allPpmps.find(p => p.id === selectedPpmpId) || ppmp;
    const isViewingHistorical = activeSelectedPpmp && ppmp && (activeSelectedPpmp.id !== ppmp.id);

    // Get routes, reviews, and logs for currently selected PPMP
    const activeRoutes = isViewingHistorical
        ? (activeSelectedPpmp.routes || [])
        : (routes.length > 0 ? routes : (ppmp?.routes || []));

    const activeChangeLogs = isViewingHistorical
        ? (activeSelectedPpmp.change_logs || [])
        : (changeLogs.length > 0 ? changeLogs : (ppmp?.change_logs || []));

    // Reverse routes so that the latest event is on the first (top), descending down to the oldest
    const sortedRoutes = React.useMemo(() => {
        return [...activeRoutes].reverse();
    }, [activeRoutes]);

    const latestRoute = activeRoutes.length > 0 ? activeRoutes[activeRoutes.length - 1] : null;

    return (
        <>
            {/* Clickable Card / Summary trigger */}
            <div className="bg-white rounded-lg border border-slate-200 shadow-sm p-4 hover:border-indigo-300 transition">
                <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div className="flex items-center gap-2">
                        <Clock className="w-5 h-5 text-indigo-600" />
                        <h3 className="font-bold text-sm text-slate-800 uppercase tracking-wide">
                            PPMP Workflow & Routing
                        </h3>
                    </div>
                    <span className="text-[11px] font-mono font-semibold px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-200">
                        {routes.length} {routes.length === 1 ? 'Action' : 'Actions'}
                    </span>
                </div>

                {latestRoute ? (
                    <div className="mt-3 text-xs text-slate-600">
                        <div className="flex items-center justify-between text-[11px] text-slate-500 mb-1">
                            <span className="font-semibold uppercase tracking-wider text-slate-700">Latest Event</span>
                            <span>{formatDate(latestRoute.acted_at || latestRoute.submitted_at)}</span>
                        </div>
                        <div className="p-2.5 bg-slate-50 rounded border border-slate-200 font-medium text-slate-800 flex items-center justify-between">
                            <span className="truncate">{latestRoute.action.replace(/_/g, ' ')}</span>
                            <span className="text-[10px] text-slate-500 shrink-0 font-mono ml-2">
                                {latestRoute.from_role} → {latestRoute.to_role}
                            </span>
                        </div>
                    </div>
                ) : (
                    <div className="mt-3 text-xs text-slate-400 italic">
                        No routing actions recorded yet.
                    </div>
                )}

                <div className="mt-4 flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setIsOpen(true)}
                        className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs uppercase tracking-wider rounded-md border border-indigo-200 transition shadow-2xs cursor-pointer"
                    >
                        <History className="w-4 h-4" />
                        View History
                    </button>
                    {onPrint && (
                        <button
                            type="button"
                            onClick={onPrint}
                            className="flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs uppercase tracking-wider rounded-md border border-slate-300 transition shadow-2xs cursor-pointer"
                            title="Print Official Document Routing Slip"
                        >
                            <Printer className="w-4 h-4 text-slate-600" />
                            Print
                        </button>
                    )}
                </div>
            </div>

            {/* Modal Dialog */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[85vh] flex flex-col border border-slate-200 animate-in fade-in zoom-in-95 duration-150 overflow-hidden">
                        {/* Modal Header */}
                        <div className="p-4 sm:px-6 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                            <div className="flex items-center gap-2.5">
                                <Clock className="w-5 h-5 text-indigo-400" />
                                <div>
                                    <h3 className="font-bold text-sm tracking-wide uppercase">
                                        PPMP Workflow & Routing History
                                    </h3>
                                    <p className="text-[11px] text-slate-400">
                                        Workflow audit trail &bull; Latest events first
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {onPrint && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setIsOpen(false);
                                            onPrint(activeSelectedPpmp);
                                        }}
                                        className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
                                        title="Print Official Document Routing Slip"
                                    >
                                        <Printer className="w-3.5 h-3.5" />
                                        <span>Print Routing Slip</span>
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        {/* Modal Body: Two-column layout with scrollable Sidebar for versions and scrollable Main Content for timeline */}
                        <div className="flex flex-col md:flex-row flex-1 overflow-hidden">
                            {/* Left Sidebar: Select Version (Scrollable if many versions) */}
                            {allPpmps.length > 1 && (
                                <div className="w-full md:w-64 bg-slate-900 border-b md:border-b-0 md:border-r border-slate-800 p-3.5 flex flex-col shrink-0">
                                    <div className="flex items-center justify-between mb-2.5 px-1">
                                        <span className="text-[11px] uppercase font-bold text-slate-400 tracking-wider flex items-center gap-1.5">
                                            <History className="w-3.5 h-3.5 text-indigo-400" />
                                            Select Version
                                        </span>
                                        <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                                            {allPpmps.length} versions
                                        </span>
                                    </div>
                                    <div className="flex md:flex-col gap-1.5 overflow-x-auto md:overflow-y-auto max-h-36 md:max-h-none flex-1 custom-scrollbar pr-0.5">
                                        {allPpmps.map((p) => {
                                            const isSelected = p.id === activeSelectedPpmp?.id;
                                            const isAnnual = p.is_annual || (!p.parent_id && Number(p.ppmp_number) === 0);
                                            const isCurrent = p.id === ppmp?.id;

                                            return (
                                                <button
                                                    key={p.id}
                                                    type="button"
                                                    onClick={() => setSelectedPpmpId(p.id)}
                                                    className={`w-full text-left p-2.5 rounded-lg text-xs font-semibold transition flex items-center justify-between gap-2 cursor-pointer border shrink-0 ${
                                                        isSelected
                                                            ? 'bg-indigo-600 text-white border-indigo-500 shadow-xs'
                                                            : 'bg-slate-800/80 text-slate-300 hover:bg-slate-800 hover:text-white border-slate-700/80'
                                                    }`}
                                                >
                                                    <div className="truncate">
                                                        <div className="font-bold flex items-center gap-1.5 truncate">
                                                            <span>PPMP No. {p.ppmp_number}</span>
                                                            <span className="text-[10px] font-normal opacity-90">
                                                                ({isAnnual ? 'Annual' : (p.amendment_type === 'AMENDMENT' ? 'Amended' : 'Supplemental')})
                                                            </span>
                                                        </div>
                                                        <div className={`text-[10px] font-mono truncate mt-0.5 ${isSelected ? 'text-indigo-200' : 'text-slate-400'}`}>
                                                            {p.tracking_number}
                                                        </div>
                                                    </div>
                                                    {isCurrent ? (
                                                        <span className={`text-[9px] px-1.5 py-0.5 rounded font-mono font-bold shrink-0 ${
                                                            isSelected ? 'bg-indigo-700 text-white' : 'bg-emerald-950 text-emerald-300 border border-emerald-800'
                                                        }`}>
                                                            Active
                                                        </span>
                                                    ) : (
                                                        <span className={`text-[9px] px-1.5 py-0.5 rounded font-mono shrink-0 ${
                                                            isSelected ? 'bg-indigo-700 text-indigo-100' : 'bg-slate-700/60 text-slate-400'
                                                        }`}>
                                                            Old
                                                        </span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Right / Main Timeline Content Area */}
                            <div className="p-6 overflow-y-auto custom-scrollbar flex-1 space-y-6 bg-white">
                            {sortedRoutes.length === 0 ? (
                                <div className="text-center py-8 text-xs text-slate-400 italic">
                                    No routing history recorded for this version yet.
                                </div>
                            ) : (
                                <div className="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                                    {sortedRoutes.map((route, idx) => {
                                        const isReturned = route.status.includes('RETURNED');
                                        const isApproved = route.status.includes('APPROVED') || route.status === 'READY_TO_PRINT';
                                        const isLatest = idx === 0;

                                        return (
                                            <div key={route.id || idx} className="relative group">
                                                {/* Dot on timeline */}
                                                <div
                                                    className={`absolute -left-[27px] top-0.5 w-4 h-4 rounded-full border-2 border-white shadow-xs flex items-center justify-center ${
                                                        isReturned
                                                            ? 'bg-rose-500 ring-2 ring-rose-200'
                                                            : isApproved
                                                            ? 'bg-emerald-500 ring-2 ring-emerald-200'
                                                            : isLatest
                                                            ? 'bg-indigo-600 ring-2 ring-indigo-200 animate-pulse'
                                                            : 'bg-blue-500 ring-2 ring-blue-200'
                                                    }`}
                                                />

                                                <div className={`bg-slate-50 border rounded-lg p-4 hover:border-slate-300 transition ${isLatest ? 'border-indigo-300 ring-1 ring-indigo-100 shadow-xs' : 'border-slate-200'}`}>
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <div className="font-bold text-xs uppercase tracking-wide text-slate-800">
                                                                {route.action.replace(/_/g, ' ')}
                                                            </div>
                                                            {isLatest && (
                                                                <span className="text-[9px] font-bold px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded border border-indigo-200 uppercase tracking-tight">
                                                                    Latest Action
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className="text-[10px] font-mono text-slate-500 bg-white px-2 py-0.5 rounded border border-slate-200 shrink-0">
                                                            {formatDate(route.acted_at || route.submitted_at || route.created_at)}
                                                        </span>
                                                    </div>

                                                    <div className="mt-1.5 flex items-center gap-2 text-xs text-slate-600">
                                                        <span className="font-semibold text-slate-900">
                                                            {route.from_user?.name || route.from_role}
                                                        </span>
                                                        <span className="text-slate-400 font-mono">→</span>
                                                        <span className="font-semibold text-slate-900">
                                                            {route.to_user?.name || route.to_role}
                                                        </span>
                                                    </div>

                                                    {/* Timestamps breakdown */}
                                                    <div className="mt-2.5 pt-2 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-1 text-[11px] text-slate-500">
                                                        <div>
                                                            <span className="font-semibold text-slate-700">Submitted:</span>{' '}
                                                            {formatDate(route.submitted_at)}
                                                        </div>
                                                        <div>
                                                            <span className="font-semibold text-slate-700">Received:</span>{' '}
                                                            {route.received_at ? (
                                                                formatDate(route.received_at)
                                                            ) : (
                                                                <span className="text-amber-600 font-medium italic">Pending Receipt</span>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Remarks if any */}
                                                    {route.remarks && (
                                                        <div className="mt-2.5 p-2.5 bg-white rounded border border-slate-200 text-xs text-slate-700 italic">
                                                            "{route.remarks}"
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {/* Field Level Change Logs */}
                            {changeLogs.length > 0 && (
                                <div className="mt-6 pt-5 border-t border-slate-200">
                                    <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">
                                        Reviewer Field-Level Change Logs
                                    </h4>
                                    <div className="space-y-2">
                                        {changeLogs.map((log) => (
                                            <div key={log.id} className="p-3 bg-amber-50/70 border border-amber-200 rounded-md text-xs">
                                                <div className="flex items-center justify-between text-[11px] font-medium text-amber-900 mb-1">
                                                    <span>Field modified: <strong>{log.field_name}</strong></span>
                                                    <span>By {log.user?.name} ({log.role})</span>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2 text-[11px] font-mono">
                                                    <div className="text-rose-700 line-through">Old: {log.old_value || '(empty)'}</div>
                                                    <div className="text-emerald-800 font-semibold">New: {log.new_value}</div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Modal Footer */}
                    <div className="p-3 bg-slate-100 border-t border-slate-200 flex items-center justify-between">
                            <div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsOpen(false)}
                                className="px-4 py-1.5 bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs rounded transition cursor-pointer"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
};
