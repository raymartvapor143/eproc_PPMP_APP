import React from 'react';

export const formatCurrency = (amount) => {
    const num = parseFloat(amount || 0);
    return '₱' + num.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

export const formatDate = (dateString) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export const getStatusConfig = (status) => {
    switch (status) {
        case 'DRAFT':
            return {
                label: 'Draft',
                bg: 'bg-slate-100 text-slate-700 border-slate-300',
                dot: 'bg-slate-400',
                desc: 'Created by End User, currently being drafted.',
            };
        case 'HEAD_PENDING':
            return {
                label: 'Pending Head Approval',
                bg: 'bg-amber-100 text-amber-800 border-amber-300',
                dot: 'bg-amber-500 animate-pulse',
                desc: 'Awaiting review and endorsement from the Office Head.',
            };
        case 'HEAD_RETURNED':
            return {
                label: 'Returned by Head',
                bg: 'bg-rose-100 text-rose-800 border-rose-300',
                dot: 'bg-rose-500',
                desc: 'Returned by Office Head with required corrections.',
            };
        case 'HEAD_APPROVED':
            return {
                label: 'Head Endorsed',
                bg: 'bg-blue-100 text-blue-800 border-blue-300',
                dot: 'bg-blue-500',
                desc: 'Endorsed by Office Head. Ready to be submitted for formal review.',
            };
        case 'BUDGET_OFFICER_REVIEW':
            return {
                label: 'Budget Officer Review',
                bg: 'bg-indigo-100 text-indigo-800 border-indigo-300',
                dot: 'bg-indigo-500 animate-pulse',
                desc: 'Awaiting budgetary review and fund allocation certification.',
            };
        case 'BUDGET_OFFICER_RETURNED':
            return {
                label: 'Returned by Budget Officer',
                bg: 'bg-rose-100 text-rose-800 border-rose-300',
                dot: 'bg-rose-500',
                desc: 'Returned by Budget Officer. Remarks need compliance.',
            };
        case 'PACCO_REVIEW':
            return {
                label: 'PACCO Review',
                bg: 'bg-teal-100 text-teal-800 border-teal-300',
                dot: 'bg-teal-500 animate-pulse',
                desc: 'Awaiting Trust Fund verification and certification from PACCO.',
            };
        case 'PACCO_RETURNED':
            return {
                label: 'Returned by PACCO',
                bg: 'bg-rose-100 text-rose-800 border-rose-300',
                dot: 'bg-rose-500',
                desc: 'Returned by PACCO Reviewer. Remarks need compliance.',
            };
        case 'PACCO_APPROVED':
            return {
                label: 'PACCO Approved',
                bg: 'bg-teal-100 text-teal-800 border-teal-300',
                dot: 'bg-teal-500',
                desc: 'Trust Fund requirements certified by PACCO.',
            };
        case 'OPPMO_REVIEW':
            return {
                label: 'OPPMO Review',
                bg: 'bg-purple-100 text-purple-800 border-purple-300',
                dot: 'bg-purple-500 animate-pulse',
                desc: 'In review with Office of the Provincial Procurement Management Officer.',
            };
        case 'OPPMO_RETURNED':
            return {
                label: 'Returned by OPPMO',
                bg: 'bg-rose-100 text-rose-800 border-rose-300',
                dot: 'bg-rose-500',
                desc: 'Returned by OPPMO for procurement method or schedule clarification.',
            };
        case 'TWG_REVIEW':
            return {
                label: 'BAC-TWG Review',
                bg: 'bg-cyan-100 text-cyan-800 border-cyan-300',
                dot: 'bg-cyan-500 animate-pulse',
                desc: 'In technical evaluation with Bids & Awards Committee TWG.',
            };
        case 'TWG_RETURNED':
            return {
                label: 'Returned by TWG',
                bg: 'bg-rose-100 text-rose-800 border-rose-300',
                dot: 'bg-rose-500',
                desc: 'Returned by Technical Working Group with findings.',
            };
        case 'READY_TO_PRINT':
            return {
                label: 'Ready to Print',
                bg: 'bg-emerald-100 text-emerald-800 border-emerald-300 shadow-sm',
                dot: 'bg-emerald-500',
                desc: 'Fully reviewed and approved by all authorities. Ready for official printing.',
            };
        default:
            return {
                label: status,
                bg: 'bg-gray-100 text-gray-700 border-gray-300',
                dot: 'bg-gray-400',
                desc: '',
            };
    }
};

export const StatusBadge = ({ status, showDot = true }) => {
    const config = getStatusConfig(status);

    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${config.bg}`}>
            {showDot && <span className={`w-1.5 h-1.5 rounded-full ${config.dot}`} />}
            {config.label}
        </span>
    );
};
