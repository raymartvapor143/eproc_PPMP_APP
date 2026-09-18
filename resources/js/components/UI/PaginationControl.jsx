import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

/**
 * Generates an array of page numbers and ellipsis strings ('...')
 * e.g., 1, 2, 3, 4, 5, 6, 7, '...', 37, 38, 39
 */
export const getPaginationPages = (currentPage, totalPages, delta = 2) => {
    if (totalPages <= 9) {
        return Array.from({ length: totalPages }, (_, i) => i + 1);
    }

    const pages = [];

    // Near the start: show 1..5 or 1..7, '...', totalPages-1, totalPages
    if (currentPage <= 5) {
        for (let i = 1; i <= Math.min(7, totalPages); i++) {
            pages.push(i);
        }
        if (totalPages > 8) {
            pages.push('...');
            pages.push(totalPages - 1);
            pages.push(totalPages);
        } else if (totalPages === 8) {
            pages.push(8);
        }
        return pages;
    }

    // Near the end: show 1, 2, '...', (totalPages-6)..totalPages
    if (currentPage >= totalPages - 4) {
        pages.push(1);
        pages.push(2);
        pages.push('...');
        for (let i = totalPages - 6; i <= totalPages; i++) {
            pages.push(i);
        }
        return pages;
    }

    // In the middle: 1, '...', curr-2, curr-1, curr, curr+1, curr+2, '...', totalPages
    pages.push(1);
    pages.push('...');
    for (let i = currentPage - 2; i <= currentPage + 2; i++) {
        pages.push(i);
    }
    pages.push('...');
    pages.push(totalPages);

    return pages;
};

export const PaginationControl = ({
    currentPage,
    totalPages,
    onPageChange,
    perPage,
    onPerPageChange,
    totalEntries,
    startIndex,
    endIndex,
}) => {
    if (totalEntries === 0) return null;

    const pages = getPaginationPages(currentPage, totalPages);

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 font-medium">
            <div className="flex items-center gap-3">
                <span>
                    Showing <span className="font-semibold text-slate-800">{startIndex + 1}</span> to{' '}
                    <span className="font-semibold text-slate-800">{Math.min(endIndex, totalEntries)}</span> of{' '}
                    <span className="font-semibold text-slate-800">{totalEntries}</span> entries
                </span>

                {onPerPageChange && (
                    <div className="flex items-center gap-1.5 ml-2">
                        <span>Show:</span>
                        <select
                            value={perPage}
                            onChange={(e) => {
                                onPerPageChange(Number(e.target.value));
                                onPageChange(1);
                            }}
                            className="py-1 px-2 border border-slate-300 rounded bg-white text-slate-700 font-semibold focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer"
                        >
                            <option value={5}>5 entries</option>
                            <option value={10}>10 entries</option>
                        </select>
                    </div>
                )}
            </div>

            <div className="flex items-center gap-1">
                <button
                    type="button"
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(Math.max(currentPage - 1, 1))}
                    className="p-1.5 rounded border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                    title="Previous Page"
                >
                    <ChevronLeft className="w-4 h-4" />
                </button>

                <div className="flex items-center gap-1 px-1">
                    {pages.map((p, idx) => {
                        if (p === '...') {
                            return (
                                <span
                                    key={`ellipsis-${idx}`}
                                    className="px-1.5 py-1 text-slate-400 font-bold tracking-widest select-none"
                                >
                                    ...
                                </span>
                            );
                        }

                        return (
                            <button
                                key={p}
                                type="button"
                                onClick={() => onPageChange(p)}
                                className={`min-w-[28px] px-2 py-1 rounded text-xs font-semibold transition cursor-pointer ${
                                    currentPage === p
                                        ? 'bg-blue-600 text-white shadow-xs'
                                        : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100'
                                }`}
                            >
                                {p}
                            </button>
                        );
                    })}
                </div>

                <button
                    type="button"
                    disabled={currentPage >= totalPages}
                    onClick={() => onPageChange(Math.min(currentPage + 1, totalPages))}
                    className="p-1.5 rounded border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                    title="Next Page"
                >
                    <ChevronRight className="w-4 h-4" />
                </button>
            </div>
        </div>
    );
};
export default PaginationControl;
