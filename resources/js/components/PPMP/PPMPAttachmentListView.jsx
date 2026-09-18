import React, { useState } from 'react';
import { formatCurrency, formatDate } from '../UI/StatusBadge';
import { Printer, ArrowLeft, Plus, Trash2, Edit3, Loader2, Sparkles, ShieldCheck } from 'lucide-react';
import { ppmpService } from '../../services/api';

/**
 * PPMPAttachmentListView:
 * Create & View flow for "PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) LIST".
 * Fetches existing data from PPMP (office, title, total budget, account code, place/payment/delivery period, items, signers),
 * allows editing/customizing row fields (unit, qty, unit cost, total cost, description, charges, delivery info, signers),
 * and provides seamless switching between Create/Edit Mode and Official Clean Printable View.
 */
export const PPMPAttachmentListView = ({ ppmp, user, canEdit = true, onBack, onGenerated }) => {
    // Check if PPMP already has stored attachment list data
    const savedData = ppmp?.attachment_list_data || null;

    // Determine edit permission: creator can edit before formal review (DRAFT, HEAD_PENDING, HEAD_APPROVED) or when returned; reviewers can edit during their review stage
    const isCreator = ppmp?.created_by === user?.id;
    const isCreatorEditable = isCreator && [
        'DRAFT',
        'HEAD_PENDING',
        'HEAD_APPROVED',
        'HEAD_RETURNED',
        'BUDGET_OFFICER_RETURNED',
        'OPPMO_RETURNED',
        'TWG_RETURNED'
    ].includes(ppmp?.status);

    const isReviewerEditable = (
        (user?.role === 'head' && ['HEAD_PENDING', 'HEAD_APPROVED'].includes(ppmp?.status) && user?.office_id === ppmp?.office_id) ||
        (user?.role === 'budget_officer' && ppmp?.status === 'BUDGET_OFFICER_REVIEW') ||
        (user?.role === 'oppmo' && ppmp?.status === 'OPPMO_REVIEW') ||
        (user?.role === 'twg' && ppmp?.status === 'TWG_REVIEW')
    );

    const allowEdit = canEdit && (isCreatorEditable || isReviewerEditable || user?.role === 'admin');

    // Mode: 'create' (form mode to edit/create) or 'view' (official print layout)
    // If user cannot edit, force view mode
    const [isEditing, setIsEditing] = useState(!savedData && allowEdit);
    const [saving, setSaving] = useState(false);

    // Initial item rows: from saved data in database, localStorage fallback, or 1 blank customizable row
    const [rows, setRows] = useState(() => {
        if (savedData?.rows && Array.isArray(savedData.rows) && savedData.rows.length > 0) {
            return savedData.rows;
        }
        const saved = localStorage.getItem(`ppmp_attachment_${ppmp?.uuid}`);
        if (saved) {
            try {
                const parsed = JSON.parse(saved);
                if (Array.isArray(parsed) && parsed.length > 0) return parsed;
            } catch (e) {}
        }
        return [
            { id: 1, itemNo: 1, unit: '', description: '', qty: '', unitCost: '', totalCost: '' }
        ];
    });

    // Signers initialized with PPMP signatories or fallback names
    const signatures = ppmp?.signatures || [];
    const preparedSig = signatures.find(s => s.role === 'end_user');
    const headSig = signatures.find(s => s.role === 'head');
    const twgSig = signatures.find(s => s.role === 'twg');

    // Form fields initialized with saved data or existing PPMP data
    const [officeName, setOfficeName] = useState(
        savedData?.office_name || ppmp?.implementing_unit || ppmp?.office?.name || 'Provincial Government of Davao del Sur'
    );
    const [projectTitle, setProjectTitle] = useState(
        savedData?.project_title || ppmp?.title || ''
    );
    const [allocatedBudget, setAllocatedBudget] = useState(
        savedData?.total_budget !== undefined
            ? parseFloat(savedData.total_budget)
            : (ppmp?.total_budget ? parseFloat(ppmp.total_budget) : 0)
    );

    const [otherTerms, setOtherTerms] = useState(
        savedData?.other_terms || ppmp?.warranty_and_other_terms || ''
    );
    // Derive default charges combining Account Code / Fund Source (e.g. "5-02-02-100 / CY 2026 General Fund")
    const rawFundSource = ppmp?.items?.find(i => !i.is_header && i.source_of_fund)?.source_of_fund?.trim() || 'General Fund';
    const fiscalYearStr = ppmp?.fiscal_year ? `CY ${ppmp.fiscal_year}` : '';
    
    // Check if rawFundSource already starts with or includes CY or a 4-digit year to avoid duplicate "CY 2026 CY 2026"
    let defaultFundSourceStr = rawFundSource;
    if (fiscalYearStr && !rawFundSource.toLowerCase().includes(fiscalYearStr.toLowerCase())) {
        defaultFundSourceStr = `${fiscalYearStr} ${rawFundSource}`.trim();
    }

    const accountCodeStr = ppmp?.account_code || '';
    const defaultChargesCombined = accountCodeStr && defaultFundSourceStr
        ? `${accountCodeStr} / ${defaultFundSourceStr}`
        : (accountCodeStr || defaultFundSourceStr || '');

    const [charges, setCharges] = useState(
        savedData?.charges || defaultChargesCombined
    );
    const [placeOfDelivery, setPlaceOfDelivery] = useState(
        savedData?.place_of_delivery || ppmp?.place_of_delivery || ''
    );
    const [paymentMethod, setPaymentMethod] = useState(
        savedData?.payment_method || ppmp?.payment_method || ''
    );
    const [deliveryPeriod, setDeliveryPeriod] = useState(
        savedData?.delivery_period || ppmp?.delivery_period || ''
    );

    // Determine office head fallback: check office.head_name, office.head.name, or office.head
    const resolvedOfficeHeadName = ppmp?.office?.head_name || ppmp?.office?.head?.name || '';
    const resolvedOfficeHeadPosition = ppmp?.office?.designation || ppmp?.office?.head?.designation || 'Head of Office';

    const [endUserName, setEndUserName] = useState(
        (savedData?.prepared_by_name && savedData.prepared_by_name !== 'NAME OF END USER')
            ? savedData.prepared_by_name
            : (preparedSig?.signer_name || ppmp?.creator?.name || 'NAME OF END USER')
    );
    const [endUserPosition, setEndUserPosition] = useState(
        (savedData?.prepared_by_position && savedData.prepared_by_position !== 'Project In-Charge')
            ? savedData.prepared_by_position
            : (preparedSig?.signer_designation || ppmp?.creator?.designation || 'Project In-Charge')
    );

    const [headName, setHeadName] = useState(
        (savedData?.submitted_by_name && savedData.submitted_by_name !== 'NAME OF HEAD')
            ? savedData.submitted_by_name
            : (headSig?.signer_name || resolvedOfficeHeadName || 'NAME OF HEAD')
    );
    const [headPosition, setHeadPosition] = useState(
        (savedData?.submitted_by_position && savedData.submitted_by_position !== 'Head of Office')
            ? savedData.submitted_by_position
            : (headSig?.signer_designation || resolvedOfficeHeadPosition || 'Head of Office')
    );

    const [approvedByName, setApprovedByName] = useState(
        savedData?.approved_by_name || ppmp?.default_signatories?.approved_by?.name || 'HON. YVONNE R. CAGAS'
    );
    const [approvedByPosition, setApprovedByPosition] = useState(
        savedData?.approved_by_position || ppmp?.default_signatories?.approved_by?.position || 'PROVINCIAL GOVERNOR'
    );

    // Row updates
    const handleUpdateRow = (index, field, value) => {
        const updated = [...rows];
        updated[index] = { ...updated[index], [field]: value };

        // If qty or unitCost changes, automatically compute totalCost
        if (field === 'qty' || field === 'unitCost') {
            const q = parseFloat(field === 'qty' ? value : updated[index].qty) || 0;
            const u = parseFloat(field === 'unitCost' ? value : updated[index].unitCost) || 0;
            if (q > 0 && u > 0) {
                updated[index].totalCost = (q * u).toFixed(2);
            }
        } else if (field === 'totalCost') {
            const t = parseFloat(value) || 0;
            const q = parseFloat(updated[index].qty) || 0;
            if (q > 0 && t > 0) {
                updated[index].unitCost = (t / q).toFixed(2);
            }
        }

        setRows(updated);
    };

    const handleAddRow = () => {
        setRows([
            ...rows,
            {
                id: Date.now(),
                itemNo: rows.length + 1,
                unit: '',
                description: '',
                qty: '',
                unitCost: '',
                totalCost: '',
            }
        ]);
    };

    const handleRemoveRow = (index) => {
        if (rows.length <= 1) {
            alert('At least one item row is required in the PPMP list.');
            return;
        }
        const updated = rows.filter((_, idx) => idx !== index).map((r, i) => ({ ...r, itemNo: i + 1 }));
        setRows(updated);
    };

    const handleGenerate = async () => {
        // Validate that at least some item description is present
        const hasContent = rows.some(r => (r.description && r.description.trim() !== '') || (r.qty && r.qty !== ''));
        if (!hasContent) {
            alert('Please add at least one item description in the PPMP attachment list.');
            return;
        }

        const payload = {
            office_name: officeName,
            project_title: projectTitle,
            total_budget: allocatedBudget,
            other_terms: otherTerms,
            charges,
            place_of_delivery: placeOfDelivery,
            payment_method: paymentMethod,
            delivery_period: deliveryPeriod,
            prepared_by_name: endUserName,
            prepared_by_position: endUserPosition,
            submitted_by_name: headName,
            submitted_by_position: headPosition,
            approved_by_name: approvedByName,
            approved_by_position: approvedByPosition,
            rows,
        };

        setSaving(true);
        try {
            if (ppmp?.uuid) {
                const response = await ppmpService.saveAttachmentList(ppmp.uuid, payload);
                localStorage.setItem(`ppmp_attachment_${ppmp.uuid}`, JSON.stringify(rows));
                localStorage.setItem(`ppmp_attachment_generated_${ppmp.uuid}`, 'true');

                setIsEditing(false);
                if (onGenerated) {
                    onGenerated(response.data?.ppmp || null);
                }
            } else {
                setIsEditing(false);
            }
        } catch (error) {
            console.error('Failed to save attachment list:', error);
            alert(error.response?.data?.message || 'Failed to save PPMP Attachment List to server. Please try again.');
        } finally {
            setSaving(false);
        }
    };

    // Calculate total from rows
    const calculatedTotal = rows.reduce((sum, r) => sum + (parseFloat(r.totalCost) || 0), 0);
    const displayTotalBudget = calculatedTotal > 0 ? calculatedTotal : allocatedBudget;

    const isReadyToPrint = ppmp?.status === 'READY_TO_PRINT';

    const handlePrint = () => {
        if (!isReadyToPrint) {
            alert('Printing is only authorized once the PPMP has reached the READY TO PRINT status after full workflow approval.');
            return;
        }
        window.print();
    };

    // Only show actual item rows (no extra blank rows)
    const viewRows = rows.filter(r => (r.unit || r.description || r.qty || r.unitCost || r.totalCost));

    return (
        <div className="bg-slate-100 min-h-screen font-sans text-slate-800">
            {/* Top Navigation / Action Bar */}
            <div className="no-print bg-slate-900 text-white px-4 sm:px-8 py-3.5 flex items-center justify-between shadow-md sticky top-0 z-30">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={onBack}
                        className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-lg border border-slate-700 transition cursor-pointer text-slate-200 hover:text-white"
                    >
                        <ArrowLeft className="w-4 h-4" /> Back to PPMP Details
                    </button>
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-slate-200">
                            PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) LIST
                        </div>
                        <div className="text-[11px] text-emerald-400 font-mono">
                            {isEditing ? 'Data Entry & Customization' : 'Official Attachment View — Portrait (A4 / Letter / Legal)'}
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2.5">
                    {isEditing ? (
                        <button
                            type="button"
                            disabled={saving}
                            onClick={handleGenerate}
                            className="flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow transition cursor-pointer disabled:opacity-50"
                        >
                            {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                            {saving ? 'Generating...' : 'Generate PPMP List of Attachment'}
                        </button>
                    ) : (
                        <>
                            {allowEdit ? (
                                <button
                                    type="button"
                                    onClick={() => setIsEditing(true)}
                                    className="flex items-center gap-1.5 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg border border-slate-700 transition cursor-pointer"
                                >
                                    <Edit3 className="w-4 h-4" />
                                    Edit Fields
                                </button>
                            ) : (
                                <div className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800/80 border border-slate-700 text-slate-400 text-xs font-medium rounded-lg" title="Document is under formal review and locked from end-user edits.">
                                    <span>Locked for Review</span>
                                </div>
                            )}
                            {isReadyToPrint ? (
                                <button
                                    type="button"
                                    onClick={handlePrint}
                                    className="flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow transition cursor-pointer"
                                >
                                    <Printer className="w-4 h-4" />
                                    Print / Save as PDF
                                </button>
                            ) : (
                                <div className="flex items-center gap-2 px-3 py-2 bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-semibold rounded-lg" title="Document must be fully approved and in READY TO PRINT status before printing.">
                                    <Printer className="w-4 h-4 opacity-60" />
                                    <span>Print available upon READY TO PRINT status</span>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* Content Area */}
            <div className="py-8 px-4 sm:px-6">
                {isEditing ? (
                    /* CREATE / EDIT FORM VIEW */
                    <div className="max-w-5xl mx-auto space-y-6">
                        {/* Information Banner */}
                        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
                            <div className="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-xs">
                                i
                            </div>
                            <div className="text-xs text-blue-900">
                                <span className="font-bold">Fetched from PPMP:</span> All initial items, project titles, allocations, and delivery requirements have been automatically loaded from your PPMP. You can adjust the item breakdowns, unit costs, charges, or signatories below.
                            </div>
                        </div>

                        {/* Top Header Card */}
                        <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-6 space-y-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 pb-2 border-b border-slate-100">
                                General Project & Allocation Details
                            </h3>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        End User / Implementing Unit:
                                    </label>
                                    <input
                                        type="text"
                                        value={officeName}
                                        onChange={(e) => setOfficeName(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        placeholder="Office Name"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Estimated Budget / Authorized Budgetary Allocation (ABC):
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={allocatedBudget}
                                        onChange={(e) => setAllocatedBudget(parseFloat(e.target.value) || 0)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>

                                <div className="md:col-span-2">
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Program / Project Title:
                                    </label>
                                    <input
                                        type="text"
                                        value={projectTitle}
                                        onChange={(e) => setProjectTitle(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        placeholder="Project Title"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Items Table Card */}
                        <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-6 space-y-4">
                            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800">
                                    PPMP List of Attachment Items
                                </h3>
                                <button
                                    type="button"
                                    onClick={handleAddRow}
                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-lg border border-blue-200 transition cursor-pointer"
                                >
                                    <Plus className="w-3.5 h-3.5" /> Add Item Row
                                </button>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-xs text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-100 text-slate-700 font-bold border-b border-slate-300 text-center text-[11px]">
                                            <th className="p-2 w-12">No.</th>
                                            <th className="p-2 w-28">Unit</th>
                                            <th className="p-2 text-left">Item and Description</th>
                                            <th className="p-2 w-20">Qty</th>
                                            <th className="p-2 w-28">Unit Cost</th>
                                            <th className="p-2 w-32">Total Cost</th>
                                            <th className="p-2 w-12"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200">
                                        {rows.map((row, index) => (
                                            <tr key={row.id || index} className="hover:bg-slate-50">
                                                <td className="p-2 text-center font-mono font-bold text-slate-600">
                                                    {index + 1}
                                                </td>
                                                <td className="p-2">
                                                    <input
                                                        type="text"
                                                        value={row.unit}
                                                        onChange={(e) => handleUpdateRow(index, 'unit', e.target.value)}
                                                        placeholder="e.g. roll, pcs, box"
                                                        className="w-full px-2 py-1.5 border border-slate-300 rounded text-xs"
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <textarea
                                                        rows={2}
                                                        value={row.description}
                                                        onChange={(e) => handleUpdateRow(index, 'description', e.target.value)}
                                                        placeholder="Item specifications and description..."
                                                        className="w-full px-2 py-1.5 border border-slate-300 rounded text-xs"
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <input
                                                        type="number"
                                                        value={row.qty}
                                                        onChange={(e) => handleUpdateRow(index, 'qty', e.target.value)}
                                                        className="w-full px-2 py-1.5 border border-slate-300 rounded text-xs text-center font-mono"
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={row.unitCost}
                                                        onChange={(e) => handleUpdateRow(index, 'unitCost', e.target.value)}
                                                        placeholder="0.00"
                                                        className="w-full px-2 py-1.5 border border-slate-300 rounded text-xs text-right font-mono"
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={row.totalCost}
                                                        onChange={(e) => handleUpdateRow(index, 'totalCost', e.target.value)}
                                                        placeholder="0.00"
                                                        className="w-full px-2 py-1.5 border border-slate-300 rounded text-xs text-right font-mono font-bold"
                                                    />
                                                </td>
                                                <td className="p-2 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleRemoveRow(index)}
                                                        className="p-1 text-slate-400 hover:text-rose-600 rounded transition"
                                                        title="Delete row"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex items-center justify-between pt-3 border-t border-slate-200">
                                <button
                                    type="button"
                                    onClick={handleAddRow}
                                    className="flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-800 cursor-pointer"
                                >
                                    <Plus className="w-3.5 h-3.5" /> Add Another Item
                                </button>
                                <div className="text-right">
                                    <span className="text-xs text-slate-500 mr-2">Computed Total Cost:</span>
                                    <span className="text-sm font-extrabold font-mono text-blue-900">
                                        {formatCurrency(calculatedTotal)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Delivery, Payment, and Charges Section */}
                        <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-6 space-y-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 pb-2 border-b border-slate-100">
                                Charges & Delivery Specifications
                            </h3>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div className="md:col-span-2">
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Other Terms: <span className="text-slate-400 font-normal text-[11px]">(Optional)</span>
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={otherTerms}
                                        onChange={(e) => setOtherTerms(e.target.value)}
                                        placeholder="e.g. Green Specification or Warranty Terms"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs leading-snug resize-y focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        (Charges: e.g. Account Code / Fund Source):
                                    </label>
                                    <input
                                        type="text"
                                        value={charges}
                                        onChange={(e) => setCharges(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                        placeholder="e.g. 5-02-02-100 / CY 2026 General Fund"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Place of Delivery:
                                    </label>
                                    <input
                                        type="text"
                                        value={placeOfDelivery}
                                        onChange={(e) => setPlaceOfDelivery(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Payment Method:
                                    </label>
                                    <input
                                        type="text"
                                        value={paymentMethod}
                                        onChange={(e) => setPaymentMethod(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Delivery Period:
                                    </label>
                                    <input
                                        type="text"
                                        value={deliveryPeriod}
                                        onChange={(e) => setDeliveryPeriod(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Signatories & Approvals Card */}
                        <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-6 space-y-4">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800 pb-2 border-b border-slate-100 flex items-center justify-between">
                                <span>Official Signatories &amp; Endorsement</span>
                                <span className="text-[11px] text-slate-400 font-normal normal-case">
                                    Auto-fetched from office profile and user roles
                                </span>
                            </h3>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
                                    <span className="font-bold text-slate-800 uppercase tracking-wide block text-[11px]">
                                        Prepared By (End User / Project In-Charge)
                                    </span>
                                    <div>
                                        <label className="block text-slate-600 mb-0.5">Full Name:</label>
                                        <input
                                            type="text"
                                            value={endUserName}
                                            onChange={(e) => setEndUserName(e.target.value)}
                                            className="w-full px-3 py-1.5 border border-slate-300 rounded text-xs bg-white"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-slate-600 mb-0.5">Designation / Position:</label>
                                        <input
                                            type="text"
                                            value={endUserPosition}
                                            onChange={(e) => setEndUserPosition(e.target.value)}
                                            className="w-full px-3 py-1.5 border border-slate-300 rounded text-xs bg-white"
                                        />
                                    </div>
                                </div>

                                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
                                    <span className="font-bold text-slate-800 uppercase tracking-wide block text-[11px]">
                                        Submitted By (Head of Office)
                                    </span>
                                    <div>
                                        <label className="block text-slate-600 mb-0.5">Head of Office Name:</label>
                                        <input
                                            type="text"
                                            value={headName}
                                            onChange={(e) => setHeadName(e.target.value)}
                                            className="w-full px-3 py-1.5 border border-slate-300 rounded text-xs bg-white font-semibold"
                                            placeholder="Name of Department / Office Head"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-slate-600 mb-0.5">Designation / Position:</label>
                                        <input
                                            type="text"
                                            value={headPosition}
                                            onChange={(e) => setHeadPosition(e.target.value)}
                                            className="w-full px-3 py-1.5 border border-slate-300 rounded text-xs bg-white"
                                            placeholder="Head of Office"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Bottom Generate Bar */}
                        <div className="flex items-center justify-end gap-3 pt-2">
                            <button
                                type="button"
                                disabled={saving}
                                onClick={onBack}
                                className="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-lg transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={saving}
                                onClick={handleGenerate}
                                className="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow transition cursor-pointer flex items-center gap-2 disabled:opacity-50"
                            >
                                {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                                {saving ? 'Generating...' : 'Generate PPMP List of Attachment'}
                            </button>
                        </div>
                    </div>
                ) : (
                    /* OFFICIAL PRINT VIEW MATCHING PORTRAIT PDF STANDARD */
                    <>
                        {/* Force portrait printing without browser header/footer */}
                        <style>{`
                            @media print {
                                @page {
                                    size: portrait !important;
                                    margin: 0 !important;
                                }
                                body {
                                    padding: 10mm !important;
                                }
                            }
                        `}</style>
                        <div className="p-8 sm:p-12 w-full max-w-[820px] mx-auto bg-white text-black shadow-xl rounded-2xl print:p-0 print:max-w-none print:shadow-none print:rounded-none border border-slate-200 print:border-none">
                            {/* Header */}
                            <div className="flex items-center justify-center gap-4 mb-4 text-center relative">
                            <img
                                src="/images/logo.png"
                                alt="Provincial Seal"
                                className="w-16 h-16 object-contain absolute left-4 top-0"
                                onError={(e) => { e.target.style.display = 'none'; }}
                            />
                            <div>
                                <div className="text-sm sm:text-base font-bold uppercase tracking-tight">
                                    PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP ) CY {ppmp?.fiscal_year || new Date().getFullYear()}
                                </div>
                                <div className="text-xs sm:text-sm font-semibold tracking-tight mt-0.5">
                                    Provincial Government of Davao del Sur
                                </div>
                                <div className="text-xs sm:text-sm font-normal tracking-tight">
                                    Matti, Digos City
                                </div>
                            </div>
                        </div>

                        {/* Metadata details */}
                        <div className="text-xs font-bold space-y-1 mb-3 pt-2">
                            <div>
                                End User/ Implementing Unit: <span className="font-semibold uppercase">{officeName}</span>
                            </div>
                            <div>
                                Program/Project Title: <span className="font-semibold uppercase">{projectTitle || '—'}</span>
                            </div>
                            {/* <div>
                                Estimated Budget / Authorized Budgetary Allocation (ABC): <span className="font-mono font-bold">{formatCurrency(displayTotalBudget)}</span>
                            </div> */}
                            <div>Charges: {charges}</div>
                        </div>

                        {/* Main Table */}
                        <table className="w-full border-collapse border border-black text-[11px]">
                            <thead>
                                <tr className="border border-black font-bold text-center bg-slate-50">
                                    <th className="border border-black px-2 py-1.5 w-14">Item No.</th>
                                    <th className="border border-black px-2 py-1.5 w-24">Unit</th>
                                    <th className="border border-black px-3 py-1.5 text-left">Item and Description</th>
                                    <th className="border border-black px-2 py-1.5 w-16">Qty</th>
                                    <th className="border border-black px-2 py-1.5 w-24">Unit Cost</th>
                                    <th className="border border-black px-2 py-1.5 w-28">Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                {viewRows.map((row, idx) => (
                                    <tr key={idx} className="h-7 border border-black">
                                        <td className="border border-black text-center px-1 py-1 font-mono">
                                            {row.itemNo}
                                        </td>
                                        <td className="border border-black text-center px-1 py-1 text-slate-700">
                                            {row.unit}
                                        </td>
                                        <td className="border border-black px-2 py-1 font-medium">
                                            {row.description}
                                        </td>
                                        <td className="border border-black text-center px-1 py-1 font-mono">
                                            {row.qty}
                                        </td>
                                        <td className="border border-black text-right px-2 py-1 font-mono">
                                            {row.unitCost ? formatCurrency(row.unitCost) : ''}
                                        </td>
                                        <td className="border border-black text-right px-2 py-1 font-mono">
                                            {row.totalCost !== null && row.totalCost !== undefined && row.totalCost !== '' ? formatCurrency(row.totalCost) : ''}
                                        </td>
                                    </tr>
                                ))}

                                {/* Charges & Delivery Info Row */}
                                <tr className="border border-black">
                                    <td colSpan={2} className="border border-black"></td>
                                    <td className="border border-black p-2 font-bold space-y-1.5 text-[11px] align-top">
                                        {otherTerms ? (
                                            <div className="mb-1.5 pb-1 border-b border-dashed border-black/30 font-normal whitespace-pre-wrap">
                                                {otherTerms}
                                            </div>
                                        ) : null}

                                        <div className="mt-1">
                                            Place of Delivery: <span className="font-normal">{placeOfDelivery}</span>
                                        </div>
                                        <div>
                                            Payment Method: <span className="font-normal">{paymentMethod}</span>
                                        </div>
                                        <div>
                                            Delivery Period: <span className="font-normal">{deliveryPeriod}</span>
                                        </div>
                                    </td>
                                    <td colSpan={2} className="border border-black p-2 text-right font-extrabold uppercase align-top">
                                        TOTAL
                                    </td>
                                    <td className="border border-black p-2 text-right font-mono font-bold align-top">
                                        {formatCurrency(calculatedTotal || displayTotalBudget)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {/* Signatures Section matching PPMP standard - borderless & compressed */}
                        <div className="mt-4 text-xs bg-white">
                            {/* Top Row: Prepared By & Submitted By */}
                            <div className="grid grid-cols-2 gap-6 mb-3">
                                <div>
                                    <div className="font-bold text-[10px] mb-1">Prepared By:</div>
                                    <div className="text-center flex flex-col items-center justify-end">
                                        {preparedSig?.user?.signature_path ? (
                                            <div className="h-8 flex items-center justify-center">
                                                <img
                                                    src={`/api/users/${preparedSig.user.id}/signature`}
                                                    alt="Signature"
                                                    className="max-h-8 max-w-[120px] object-contain"
                                                />
                                            </div>
                                        ) : preparedSig?.signature_indicator ? (
                                            <div className="inline-flex flex-col items-center mb-1">
                                                <div className="flex items-center gap-1.5 px-2 py-0.5 bg-emerald-50 border border-emerald-300 rounded text-emerald-800 text-[10px] font-mono">
                                                    <ShieldCheck className="w-3 h-3 text-emerald-600" />
                                                    <span>{preparedSig.signature_indicator}</span>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="h-2" />
                                        )}
                                        <div className="font-bold text-xs uppercase underline">
                                            {endUserName}
                                        </div>
                                        <div className="text-[10px] text-slate-600">
                                            {endUserPosition}
                                        </div>
                                        {preparedSig?.signed_at && (
                                            <div className="text-[9px] text-slate-500">
                                                Date/Time: {formatDate(preparedSig.signed_at)}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <div className="font-bold text-[10px] mb-1">Submitted By:</div>
                                    <div className="text-center flex flex-col items-center justify-end">
                                        {headSig?.user?.signature_path ? (
                                            <div className="h-8 flex items-center justify-center">
                                                <img
                                                    src={`/api/users/${headSig.user.id}/signature`}
                                                    alt="Signature"
                                                    className="max-h-8 max-w-[120px] object-contain"
                                                />
                                            </div>
                                        ) : headSig?.signature_indicator ? (
                                            <div className="inline-flex flex-col items-center mb-1">
                                                <div className="flex items-center gap-1.5 px-2 py-0.5 bg-blue-50 border border-blue-300 rounded text-blue-800 text-[10px] font-mono">
                                                    <ShieldCheck className="w-3 h-3 text-blue-600" />
                                                    <span>{headSig.signature_indicator}</span>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="h-2" />
                                        )}
                                        <div className="font-bold text-xs uppercase underline">
                                            {headName && headName !== 'NAME OF HEAD' ? headName : (resolvedOfficeHeadName || 'NAME OF HEAD')}
                                        </div>
                                        <div className="text-[10px] text-slate-600">
                                            {headPosition && headPosition !== 'Head of Office' ? headPosition : (resolvedOfficeHeadPosition || 'Head of Office')}
                                        </div>
                                        {headSig?.signed_at && (
                                            <div className="text-[9px] text-slate-500">
                                                Date/Time: {formatDate(headSig.signed_at)}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Bottom Row: REVIEWED BY TWG (Automatically fetched from TWG Review / Signatures) */}
                            <div className="pt-2">
                                <div className="text-center flex flex-col items-center justify-end">
                                    <div className="font-bold text-[10px] uppercase mb-1">REVIEWED BY TWG:</div>
                                    {twgSig?.user?.signature_path ? (
                                        <div className="h-8 flex items-center justify-center">
                                            <img
                                                src={`/api/users/${twgSig.user.id}/signature`}
                                                alt="Signature"
                                                className="max-h-8 max-w-[120px] object-contain"
                                            />
                                        </div>
                                    ) : twgSig?.signature_indicator ? (
                                        <div className="inline-flex flex-col items-center mb-1">
                                            <div className="flex items-center gap-1.5 px-2 py-0.5 bg-cyan-50 border border-cyan-300 rounded text-cyan-800 text-[10px] font-mono">
                                                <ShieldCheck className="w-3 h-3 text-cyan-600" />
                                                <span>{twgSig.signature_indicator}</span>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="h-2" />
                                    )}
                                    <div className="font-bold text-xs uppercase underline">
                                        {twgSig?.signer_name || twgSig?.user?.name || 'NAME OF TWG INCHARGE'}
                                    </div>
                                    <div className="text-[10px] text-slate-600">
                                        {twgSig?.signer_designation || twgSig?.user?.designation || 'BAC TWG'}
                                    </div>
                                    {twgSig?.signed_at && (
                                        <div className="text-[9px] text-slate-500">
                                            Date/Time: {formatDate(twgSig.signed_at)}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* System Generated / E-Signature Validity Notice */}
                            <div className="mt-4 pt-2 border-t border-dotted border-slate-300 text-center">
                                <p className="text-[8px] italic text-slate-600 font-sans tracking-wide">
                                    * This is an electronically generated and certified document under the Electronic Procurement Management System. Valid even without a physical or handwritten signature.
                                </p>
                            </div>
                        </div>
                    </div>
                </>
                )}
            </div>
        </div>
    );
};
