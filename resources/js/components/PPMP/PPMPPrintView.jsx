import React from 'react';
import { formatCurrency, formatDate } from '../UI/StatusBadge';
import { Printer, ArrowLeft } from 'lucide-react';
import { formatDescription6Words, formatMonthYear } from './ProcurementPlanTable';

export const PPMPPrintView = ({ ppmp, onBack }) => {
    const handlePrint = () => {
        window.print();
    };

    const items = ppmp.items || [];
    // Only render actual PPMP items with no extra blank placeholder rows
    const displayRows = items;

    const signatures = ppmp.signatures || [];
    const preparedSig = signatures.find(s => s.role === 'end_user');
    const headSig = signatures.find(s => s.role === 'head');
    const budgetSig = signatures.find(s => s.role === 'budget_officer');
    const oppmoSig = signatures.find(s => s.role === 'oppmo');
    const twgSig = signatures.find(s => s.role === 'twg');

    return (
        <div className="bg-white min-h-screen font-sans text-black">
            {/* Top Action Bar (Hidden in Print) */}
            <div className="no-print bg-slate-900 text-white px-6 py-3 flex items-center justify-between shadow-md">
                <div className="flex items-center gap-3">
                    <button
                        onClick={onBack}
                        className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded border border-slate-700 transition"
                    >
                        <ArrowLeft className="w-4 h-4" /> Back to Workspace
                    </button>
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-slate-200">
                            Official Print Layout — Province of Davao del Sur PPMP
                        </div>
                        <div className="text-[11px] text-emerald-400 font-mono">
                            Ready for Landscape / Legal / A4 Printing
                        </div>
                    </div>
                </div>

                <button
                    onClick={handlePrint}
                    className="flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-wider rounded shadow transition cursor-pointer"
                >
                    <Printer className="w-4 h-4" />
                    PRINT PPMP NOW
                </button>
            </div>

            {/* Exact PDF Layout Container */}
            <div className="p-4 sm:p-8 max-w-[1550px] mx-auto text-black bg-white">
                {/* Header Grid matching PDF */}
                <table className="w-full border-collapse mb-1">
                    <tbody>
                        <tr>
                            <td className="w-24 text-center align-middle p-1">
                                <img
                                    src="/images/logo.png"
                                    alt="Province of Davao del Sur Official Seal"
                                    className="w-20 h-20 mx-auto object-contain"
                                />
                            </td>
                            <td className="text-center align-middle p-1">
                                <div className="text-xs uppercase font-normal tracking-wide leading-tight">Republic of the Philippines</div>
                                <div className="text-base font-bold uppercase tracking-wider leading-tight">PROVINCE OF DAVAO DEL SUR</div>
                                <div className="text-sm font-bold tracking-tight mt-0.5">
                                    PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) NO. <span className="font-mono">{ppmp.ppmp_number}</span>
                                </div>
                                <div className="text-sm font-normal tracking-tight mt-0.5">
                                    Matti, Digos City
                                </div>
                                <div className="flex items-center justify-center gap-10 text-xs font-bold mt-1">
                                    <label className="flex items-center gap-1.5 cursor-default">
                                        <span className={`w-3.5 h-3.5 border border-black inline-flex items-center justify-center text-[10px] leading-none ${ppmp.plan_type === 'INDICATIVE' ? 'bg-black text-white font-black' : 'bg-white'}`}>
                                            {ppmp.plan_type === 'INDICATIVE' ? '✓' : ''}
                                        </span>
                                        <span>INDICATIVE</span>
                                    </label>
                                    <label className="flex items-center gap-1.5 cursor-default">
                                        <span className={`w-3.5 h-3.5 border border-black inline-flex items-center justify-center text-[10px] leading-none ${ppmp.plan_type === 'FINAL' ? 'bg-black text-white font-black' : 'bg-white'}`}>
                                            {ppmp.plan_type === 'FINAL' ? '✓' : ''}
                                        </span>
                                        <span>FINAL</span>
                                    </label>
                                </div>
                            </td>
                            <td className="w-24"></td>
                        </tr>
                    </tbody>
                </table>

                {/* Fiscal Year & End-User Subheaders */}
                <div className="text-xs mb-1">
                    <div className="font-bold">
                        Fiscal Year : <span className="font-normal">(CY {ppmp.fiscal_year})</span>
                    </div>
                    <div className="font-bold mt-0.5">
                        End-User or Implementing Unit: <span className="font-normal uppercase">({ppmp.implementing_unit || ppmp.office?.name || ppmp.title})</span>
                    </div>
                </div>

                {/* Exact 12-column Table Structure from PDF */}
                <table className="w-full border-collapse border border-black text-black text-[9px] leading-tight print-table">
                    <thead>
                        <tr className="text-center font-bold border-b border-black">
                            <th className="border border-black p-1 w-[15%]">
                                General Description and Objective of the<br />
                                Project to be Procured
                            </th>
                            <th className="border border-black p-1 w-[8%]">
                                Type of the Project to be<br />
                                Procured (whether Goods, Infrastructure and<br />
                                Consulting Services)
                            </th>
                            <th className="border border-black p-1 w-[7%]">
                                Quantity and Size of the Project<br />
                                to be Procured
                            </th>
                            <th className="border border-black p-1 w-[9%]">
                                Recommended Mode of<br />
                                Procurement
                            </th>
                            <th className="border border-black p-1 w-[6%]">
                                Pre-Procurement Conference, if<br />
                                applicable (Yes/No)
                            </th>
                            <th className="border border-black p-1 w-[6%]">
                                Start of Procurement<br />
                                Activity
                            </th>
                            <th className="border border-black p-1 w-[6%]">
                                End of Procurement<br />
                                Activity
                            </th>
                            <th className="border border-black p-1 w-[7%]">
                                Expected Delivery/<br />
                                Implementation Period
                            </th>
                            <th className="border border-black p-1 w-[7%]">
                                Source of Funds
                            </th>
                            <th className="border border-black p-1 w-[9%]">
                                Estimated Budget / Authorized<br />
                                Budgetary Allocation (PhP)
                            </th>
                            <th className="border border-black p-1 w-[10%]">
                                ATTACHED SUPPORTING<br />
                                DOCUMENTS
                            </th>
                            <th className="border border-black p-1 w-[10%]">
                                REMARKS
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {/* 2nd row immediately after table header row: Category / Account Title */}
                        <tr className="min-h-6 font-bold bg-white">
                            <td className="border border-black px-2 py-1.5 align-top text-center font-bold text-[9px] leading-tight whitespace-pre-wrap">
                                {formatDescription6Words(ppmp.account_code || displayRows.find(r => r.is_header)?.description || '')}
                            </td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                            <td className="border border-black"></td>
                        </tr>

                        {displayRows.filter(r => !r.is_header).map((row, idx) => (
                            <tr key={idx} className="min-h-6">
                                <td className="border border-black px-2 py-1.5 align-top text-center font-bold text-[9px] uppercase leading-relaxed whitespace-pre-wrap">
                                    {formatDescription6Words(row.description)}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center">
                                    {row.project_type}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center">
                                    {row.quantity_size}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center">
                                    {row.procurement_mode}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center font-bold">
                                    {row.pre_proc_conference === true ? 'Yes' : row.pre_proc_conference === false ? 'No' : ''}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center">
                                    {row.start_date ? formatMonthYear(row.start_date) : ''}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center">
                                    {row.end_date ? formatMonthYear(row.end_date) : ''}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center">
                                    {row.delivery_period ? formatMonthYear(row.delivery_period) : ''}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-center">
                                    {row.source_of_fund}
                                </td>
                                <td className="border border-black px-1.5 py-1 align-top text-right font-mono font-bold">
                                    {row.estimated_budget !== null && row.estimated_budget !== undefined && row.estimated_budget > 0 ? formatCurrency(row.estimated_budget) : ''}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-left text-[8px]">
                                    {row.supporting_docs_text}
                                </td>
                                <td className="border border-black px-1 py-1 align-top text-left text-[8px]">
                                    {row.remarks}
                                </td>
                            </tr>
                        ))}

                        {/* Bottom Total Budget Summary Row */}
                        <tr className="border-t border-black font-semibold text-xs bg-slate-50">
                            <td colSpan={9} className="border border-black p-2 text-right font-bold uppercase tracking-wider align-middle">
                                Total Estimated Budget:
                            </td>
                            <td colSpan={3} className="border border-black p-2 text-center align-middle bg-slate-100">
                                <div className="text-[10px] font-extrabold uppercase tracking-wider text-black">TOTAL BUDGET:</div>
                                <div className="text-sm font-extrabold font-mono mt-0.5">
                                    {formatCurrency(ppmp.total_budget)}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                {/* Signature Block matching exact 4 sections from the PDF without cell outlines and compressed */}
                <table className="w-full border-collapse border-0 text-black text-[9px] mt-1 print-signature-box">
                    <tbody>
                        {/* Top row: Prepared By & Submitted By */}
                        <tr>
                            <td className="w-1/2 p-1 align-top">
                                <div className="font-bold text-[9px]">Prepared By:</div>
                                <div className="text-center flex flex-col items-center justify-end">
                                    {preparedSig?.user?.signature_path ? (
                                        <div className="h-8 flex items-center justify-center">
                                            <img
                                                src={`/api/users/${preparedSig.user.id}/signature`}
                                                alt="Signature"
                                                className="max-h-8 max-w-[120px] object-contain"
                                            />
                                        </div>
                                    ) : (
                                        <div className="h-2" />
                                    )}
                                    <div className="font-bold text-xs uppercase underline">
                                        {preparedSig?.signer_name || 'NAME'}
                                    </div>
                                    <div className="text-[9px] uppercase">
                                        {preparedSig?.signer_designation || 'Designation'}
                                    </div>
                                    {preparedSig && (
                                        <div className="text-[8px] font-mono text-slate-600">
                                            [{preparedSig.signature_indicator}] • {formatDate(preparedSig.signed_at)}
                                        </div>
                                    )}
                                </div>
                            </td>
                            <td className="w-1/2 p-1 align-top">
                                <div className="font-bold text-[9px]">Submitted By:</div>
                                <div className="text-center flex flex-col items-center justify-end">
                                    {headSig?.user?.signature_path ? (
                                        <div className="h-8 flex items-center justify-center">
                                            <img
                                                src={`/api/users/${headSig.user.id}/signature`}
                                                alt="Signature"
                                                className="max-h-8 max-w-[120px] object-contain"
                                            />
                                        </div>
                                    ) : (
                                        <div className="h-2" />
                                    )}
                                    <div className="font-bold text-xs uppercase underline">
                                        {headSig?.signer_name || ppmp?.office?.head_name || ppmp?.office?.head?.name || 'NAME'}
                                    </div>
                                    <div className="text-[9px] uppercase font-bold">
                                        {headSig?.signer_designation || ppmp?.office?.designation || ppmp?.office?.head?.designation || 'HEAD OF OFFICE'}
                                    </div>
                                    {headSig && (
                                        <div className="text-[8px] font-mono text-slate-600">
                                            [{headSig.signature_indicator}] • {formatDate(headSig.signed_at)}
                                        </div>
                                    )}
                                </div>
                            </td> 
                        </tr>

                        {/* Spacer row between signature tiers */}
                        <tr>
                            <td colSpan={2} className="py-2"></td>
                        </tr>
                        <tr>
                            <td colSpan={2} className="py-2"></td>
                        </tr>

                        {/* Bottom row: Reviewed as to Budgetary Requirement & Reviewed by BAC-Secretariat */}
                        <tr>
                            <td className="w-1/2 p-1 align-top pt-2">
                                <div className="font-bold text-[9px] mb-1">Reviewed as to Budgetary Requirement</div>
                                <div className="text-center flex flex-col items-center justify-end">
                                    <div className="relative inline-flex items-center justify-center">
                                        <div className="font-bold text-xs uppercase underline text-center">
                                            {budgetSig?.signer_name || ppmp?.default_signatories?.budget_requirement?.name || 'Atty. Roberto G. Almendras, CPA'}
                                        </div>
                                        {budgetSig?.user?.signature_path && (
                                            <img
                                                src={`/api/users/${budgetSig.user.id}/signature`}
                                                alt="Signature"
                                                className="h-7 max-w-[65px] object-contain absolute left-full ml-1.5 bottom-0 pointer-events-none"
                                            />
                                        )}
                                    </div>
                                    <div className="text-[9px] uppercase mt-0.5">
                                        {budgetSig?.signer_designation || ppmp?.default_signatories?.budget_requirement?.position || 'PROVINCIAL BUDGET OFFICER'}
                                    </div>
                                    {budgetSig ? (
                                        <div className="text-[8px] font-mono text-slate-600">
                                            [{budgetSig.signature_indicator}] • {formatDate(budgetSig.signed_at)}
                                        </div>
                                    ) : (
                                        <div className="text-[8px] text-slate-400 italic">
                                             Awaiting Certification
                                         </div>
                                    )}
                                </div>
                            </td>
                            <td className="w-1/2 p-1 align-top pt-2">
                                <div className="font-bold text-[9px] mb-1">Reviewed by BAC- Secretariat</div>
                                <div className="text-center flex flex-col items-center justify-end">
                                    <div className="relative inline-flex items-center justify-center">
                                        <div className="font-bold text-xs uppercase underline text-center">
                                            {oppmoSig?.signer_name || ppmp?.default_signatories?.bac_secretariat?.name || 'Mr. Christopher B. Ramos'}
                                        </div>
                                        {oppmoSig?.user?.signature_path && (
                                            <img
                                                src={`/api/users/${oppmoSig.user.id}/signature`}
                                                alt="Signature"
                                                className="h-7 max-w-[65px] object-contain absolute left-full ml-1.5 bottom-0 pointer-events-none"
                                            />
                                        )}
                                    </div>
                                    <div className="text-[9px] uppercase font-bold mt-0.5">
                                        {oppmoSig?.signer_designation || ppmp?.default_signatories?.bac_secretariat?.position || 'HEAD OF BAC SECRETARIAT / OPPMO'}
                                    </div>
                                    {oppmoSig ? (
                                        <div className="text-[8px] font-mono text-slate-600">
                                            [{oppmoSig.signature_indicator}] • {formatDate(oppmoSig.signed_at)}
                                        </div>
                                    ) : (
                                        <div className="text-[8px] text-slate-400 italic">
                                             Awaiting Verification
                                         </div>
                                    )}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                {/* System Generated / E-Signature Validity Notice */}
                <div className="mt-4 pt-2 border-t border-dotted border-slate-300 text-center">
                    <p className="text-[8px] italic text-slate-600 font-sans tracking-wide">
                        * This is an electronically generated and certified document under the Electronic Procurement Management System. Valid even without a physical or handwritten signature.
                    </p>
                </div>
            </div>
        </div>
    );
};
