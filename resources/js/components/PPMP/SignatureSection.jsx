import React from 'react';
import { formatCurrency, formatDate } from '../UI/StatusBadge';
import { CheckCircle, ShieldCheck, PenTool } from 'lucide-react';

export const SignatureSection = ({ ppmp, isPrintMode = false }) => {
    const signatures = ppmp?.signatures || [];

    // Signatures mapping
    const preparedSig = signatures.find(s => s.role === 'end_user');
    const headSig = signatures.find(s => s.role === 'head');
    const budgetSig = signatures.find(s => s.role === 'budget_officer');
    const oppmoSig = signatures.find(s => s.role === 'oppmo');
    const twgSig = signatures.find(s => s.role === 'twg');

    return (
        <div className={`mt-6 border border-slate-900 bg-white ${isPrintMode ? 'print-signature-box' : 'shadow-sm rounded-lg overflow-hidden'}`}>
            {/* Top row: Prepared By & Submitted By */}
            <div className="grid grid-cols-2 divide-x divide-slate-900 border-b border-slate-900">
                {/* Prepared By (End User) */}
                <div className="p-4 flex flex-col justify-between min-h-[140px]">
                    <div className="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Prepared By:
                    </div>

                    <div className="text-center my-2">
                        {preparedSig ? (
                            <div className="inline-flex flex-col items-center">
                                {preparedSig?.user?.signature_path ? (
                                    <div className="h-10 flex items-center justify-center mb-1">
                                        <img
                                            src={`/api/users/${preparedSig.user.id}/signature`}
                                            alt="Signature"
                                            className="max-h-10 max-w-[130px] object-contain"
                                        />
                                    </div>
                                ) : !isPrintMode ? (
                                    <div className="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 border border-emerald-300 rounded text-emerald-800 text-[11px] font-mono mb-1">
                                        <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                                        <span>{preparedSig.signature_indicator}</span>
                                    </div>
                                ) : null}
                                <div className="font-bold text-sm uppercase text-slate-900 underline decoration-slate-400 underline-offset-4">
                                    {preparedSig.signer_name}
                                </div>
                                <div className="text-xs text-slate-600 font-medium">
                                    {preparedSig.signer_designation || 'Project End-User / In-Charge'}
                                </div>
                                <div className="text-[10px] text-slate-500 mt-0.5">
                                    Date/Time: {formatDate(preparedSig.signed_at)}
                                </div>
                            </div>
                        ) : (
                            <div className="h-14 flex items-center justify-center text-xs text-slate-400 italic">
                                Pending End-User Preparation
                            </div>
                        )}
                    </div>
                </div>

                {/* Submitted By (Head of Office) */}
                <div className="p-4 flex flex-col justify-between min-h-[140px]">
                    <div className="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Submitted By:
                    </div>

                    <div className="text-center my-2">
                        {headSig ? (
                            <div className="inline-flex flex-col items-center">
                                {headSig?.user?.signature_path ? (
                                    <div className="h-10 flex items-center justify-center mb-1">
                                        <img
                                            src={`/api/users/${headSig.user.id}/signature`}
                                            alt="Signature"
                                            className="max-h-10 max-w-[130px] object-contain"
                                        />
                                    </div>
                                ) : !isPrintMode ? (
                                    <div className="flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 border border-blue-300 rounded text-blue-800 text-[11px] font-mono mb-1">
                                        <ShieldCheck className="w-3.5 h-3.5 text-blue-600" />
                                        <span>{headSig.signature_indicator}</span>
                                    </div>
                                ) : null}
                                <div className="font-bold text-sm uppercase text-slate-900 underline decoration-slate-400 underline-offset-4">
                                    {headSig.signer_name}
                                </div>
                                <div className="text-xs text-slate-600 font-bold uppercase">
                                    {headSig.signer_designation || ppmp?.office?.designation || ppmp?.office?.head?.designation || 'HEAD OF OFFICE'}
                                </div>
                                <div className="text-[10px] text-slate-500 mt-0.5">
                                    Date/Time: {formatDate(headSig.signed_at)}
                                </div>
                            </div>
                        ) : (
                            <div className="h-14 flex items-center justify-center text-xs text-slate-400 italic">
                                {ppmp?.status === 'HEAD_PENDING' ? 'Awaiting Head Endorsement & E-Signature' : 'Pending Submission'}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Bottom row: Reviewed as to Budgetary Requirement & Reviewed by BAC-Secretariat / OPPMO / TWG */}
            <div className="grid grid-cols-2 divide-x divide-slate-900">
                {/* Reviewed as to Budgetary Requirement (Budget Officer Initial beside name) */}
                <div className="p-4 flex flex-col justify-between min-h-[140px]">
                    <div className="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Reviewed as to Budgetary Requirement:
                    </div>

                    <div className="text-center my-2">
                        {budgetSig ? (
                            <div className="inline-flex flex-col items-center">
                                <div className="flex items-center justify-center gap-2">
                                    <span className="font-bold text-sm uppercase text-slate-900 underline decoration-slate-400 underline-offset-4">
                                        {budgetSig.signer_name}
                                    </span>
                                    {budgetSig?.user?.signature_path && (
                                        <img
                                            src={`/api/users/${budgetSig.user.id}/signature`}
                                            alt="Signature"
                                            className="h-8 max-w-[70px] object-contain"
                                        />
                                    )}
                                </div>
                                <div className="text-xs text-slate-600 font-medium mt-0.5">
                                    {budgetSig.signer_designation || 'Provincial Budget Officer'}
                                </div>
                                <div className="flex items-center justify-center gap-1.5 mt-1">
                                    <span className="px-2 py-0.5 bg-indigo-100 border border-indigo-400 text-indigo-900 font-mono font-bold text-[10px] rounded">
                                        [{budgetSig.signature_indicator}]
                                    </span>
                                    <span className="text-[10px] text-slate-500">
                                        • {formatDate(budgetSig.signed_at)}
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-1">
                                <div className="font-bold text-sm uppercase text-slate-700">
                                    {ppmp?.default_signatories?.budget_requirement?.name || 'Atty. Roberto G. Almendras, CPA'}
                                </div>
                                <div className="text-xs text-slate-500 font-medium">
                                    {ppmp?.default_signatories?.budget_requirement?.position || 'Provincial Budget Officer'}
                                </div>
                                <div className="text-[11px] text-amber-600 font-medium mt-1">
                                    Pending Budget Officer
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Reviewed by BAC-Secretariat (OPPMO Initial) */}
                <div className="p-4 flex flex-col justify-between min-h-[140px]">
                    <div className="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Reviewed by BAC- Secretariat:
                    </div>

                    <div className="space-y-3 my-1">
                        {/* OPPMO Initial Section */}
                        {oppmoSig ? (
                            <div className="text-center">
                                <div className="flex items-center justify-center gap-2">
                                    <span className="font-bold text-sm uppercase text-slate-900 underline decoration-slate-400 underline-offset-4">
                                        {oppmoSig.signer_name}
                                    </span>
                                    {oppmoSig?.user?.signature_path && (
                                        <img
                                            src={`/api/users/${oppmoSig.user.id}/signature`}
                                            alt="Signature"
                                            className="h-8 max-w-[70px] object-contain"
                                        />
                                    )}
                                </div>
                                <div className="text-[11px] text-slate-600 mt-0.5">
                                    {oppmoSig.signer_designation || 'HEAD OF BAC SECRETARIAT / OPPMO'}
                                </div>
                                <div className="flex items-center justify-center gap-1.5 mt-1">
                                    <span className="px-2 py-0.5 bg-purple-100 border border-purple-400 text-purple-900 font-mono font-bold text-[10px] rounded">
                                        [{oppmoSig.signature_indicator}]
                                    </span>
                                    <span className="text-[10px] text-slate-500">
                                        • {formatDate(oppmoSig.signed_at)}
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-1">
                                <div className="font-bold text-sm uppercase text-slate-700">
                                    {ppmp?.default_signatories?.bac_secretariat?.name || 'Mr. Christopher B. Ramos'}
                                </div>
                                <div className="text-[11px] text-slate-500 font-medium">
                                    {ppmp?.default_signatories?.bac_secretariat?.position || 'Head of BAC Secretariat / OPPMO'}
                                </div>
                                <div className="text-[11px] text-amber-600 font-medium mt-0.5">
                                    Pending BAC Secretariat Review
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Electronic certification statement */}
            <div className="border-t border-slate-200 bg-slate-50/50 py-1.5 px-3 text-center">
                <p className="text-[9px] italic text-slate-500">
                    * This is an electronically generated and certified document under the Electronic Procurement Management System. Valid even without a physical or handwritten signature.
                </p>
            </div>
        </div>
    );
};
