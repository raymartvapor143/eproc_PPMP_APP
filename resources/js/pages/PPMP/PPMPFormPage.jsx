import React, { useState } from 'react';
import { ppmpService } from '../../services/api';
import { ProcurementPlanTable } from '../../components/PPMP/ProcurementPlanTable';
import { ArrowLeft, Save, Send, AlertCircle, FileCheck } from 'lucide-react';

export const PPMPFormPage = ({ user, initialPpmp = null, onBack, onSaved }) => {
    const isEditMode = !!initialPpmp;

    const [ppmpNumber, setPpmpNumber] = useState(initialPpmp?.ppmp_number !== undefined ? initialPpmp.ppmp_number : '');
    const [title, setTitle] = useState(initialPpmp?.title || '');
    const [accountCode, setAccountCode] = useState(initialPpmp?.account_code || '');
    const [fiscalYear, setFiscalYear] = useState(initialPpmp?.fiscal_year || new Date().getFullYear().toString());
    const [planType, setPlanType] = useState(initialPpmp?.plan_type || 'INDICATIVE');
    const [implementingUnit, setImplementingUnit] = useState(initialPpmp?.implementing_unit || initialPpmp?.office?.name || '');
    const [deliveryPeriod, setDeliveryPeriod] = useState(initialPpmp?.delivery_period || '');
    const [placeOfDelivery, setPlaceOfDelivery] = useState(initialPpmp?.place_of_delivery || '');
    const [paymentMethod, setPaymentMethod] = useState(initialPpmp?.payment_method || '');
    const [warrantyAndOtherTerms, setWarrantyAndOtherTerms] = useState(initialPpmp?.warranty_and_other_terms || '');

    const [items, setItems] = useState(initialPpmp?.items?.length ? initialPpmp.items : [
        {
            description: '',
            project_type: 'Goods',
            quantity_size: '',
            procurement_mode: 'Competitive Bidding (RA 12009)',
            pre_proc_conference: false,
            start_date: '',
            end_date: '',
            delivery_period: '',
            source_of_fund: 'General Fund',
            estimated_budget: 0,
            supporting_docs_text: '',
            remarks: '',
        }
    ]);

    const [loading, setLoading] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');

    const totalBudget = items.reduce((sum, item) => sum + (parseFloat(item.estimated_budget) || 0), 0);

    const handleAddItem = (customItem = null) => {
        if (customItem && customItem.is_header) {
            setItems([
                ...items,
                {
                    is_header: true,
                    description: customItem.description || '',
                    project_type: '',
                    quantity_size: '',
                    procurement_mode: '',
                    pre_proc_conference: false,
                    start_date: '',
                    end_date: '',
                    delivery_period: '',
                    source_of_fund: '',
                    estimated_budget: 0,
                    supporting_docs_text: '',
                    remarks: '',
                }
            ]);
            return;
        }

        setItems([
            ...items,
            {
                is_header: false,
                description: '',
                project_type: 'Goods',
                quantity_size: '',
                procurement_mode: 'Competitive Bidding (RA 12009)',
                pre_proc_conference: false,
                start_date: '',
                end_date: '',
                delivery_period: '',
                source_of_fund: 'General Fund',
                estimated_budget: 0,
                supporting_docs_text: '',
                remarks: '',
            }
        ]);
    };

    const handleRemoveItem = (index) => {
        if (items.length <= 1) {
            alert('A PPMP must contain at least one procurement item.');
            return;
        }
        setItems(items.filter((_, idx) => idx !== index));
    };

    const handleUpdateItem = (index, field, value) => {
        const updated = [...items];
        updated[index] = { ...updated[index], [field]: value };
        setItems(updated);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrorMessage('');

        // Basic validation
        if (!title.trim()) {
            setErrorMessage('Please provide a descriptive Project Title / Objective.');
            return;
        }

        const invalidItem = items.filter(i => !i.is_header).find(item => !item.description?.trim());
        if (invalidItem) {
            setErrorMessage('All item rows must have a description.');
            return;
        }

        setLoading(true);

        const payload = {
            ppmp_number: ppmpNumber.trim(),
            title: title.trim(),
            account_code: accountCode.trim(),
            fiscal_year: fiscalYear,
            plan_type: planType,
            implementing_unit: implementingUnit.trim(),
            delivery_period: deliveryPeriod,
            place_of_delivery: placeOfDelivery,
            payment_method: paymentMethod,
            warranty_and_other_terms: warrantyAndOtherTerms,
            items: items.map(item => ({
                ...item,
                estimated_budget: parseFloat(item.estimated_budget) || 0,
            })),
        };

        try {
            let res;
            if (isEditMode) {
                res = await ppmpService.update(initialPpmp.uuid, payload);
            } else {
                res = await ppmpService.create(payload);
            }

            if (onSaved) onSaved(res.data.ppmp);
        } catch (err) {
            setErrorMessage(err.response?.data?.message || 'Failed to save PPMP draft.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Action Bar */}
            <div className="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-md hover:bg-slate-100 transition"
                >
                    <ArrowLeft className="w-4 h-4" /> Back
                </button>

                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={loading}
                        className="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm transition disabled:opacity-50"
                    >
                        <Save className="w-4 h-4" />
                        {loading ? 'Saving...' : isEditMode ? 'Save Changes' : 'Save as Draft'}
                    </button>
                </div>
            </div>

            {errorMessage && (
                <div className="p-4 bg-rose-50 border border-rose-200 rounded-lg flex items-center gap-3 text-rose-800 text-xs font-medium">
                    <AlertCircle className="w-5 h-5 shrink-0 text-rose-600" />
                    <span>{errorMessage}</span>
                </div>
            )}

            {/* Official PPMP Header Form Block (Province of Davao del Sur) */}
            <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
                <div className="border-b border-slate-200 pb-4 mb-6 text-center">
                    <span className="text-xs uppercase font-medium text-slate-500 tracking-wider">
                        Republic of the Philippines
                    </span>
                    <h2 className="text-lg font-black uppercase text-slate-900 tracking-wide">
                        PROVINCE OF DAVAO DEL SUR
                    </h2>
                    <div className="text-xs font-bold text-blue-900 uppercase tracking-widest mt-0.5">
                        PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP)
                    </div>
                </div>

                {/* Form fields: PPMP Number, Fiscal Year, Plan Type, Implementing Office */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                    <div>
                        <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                            PPMP No.: <span className="text-rose-600">*</span>
                        </label>
                        <input
                            type="text"
                            required
                            value={ppmpNumber}
                            onChange={(e) => setPpmpNumber(e.target.value)}
                            className="w-full text-xs p-2.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold text-slate-900"
                            placeholder="e.g. 0, 1, 2, 3..."
                        />
                        <span className="text-[10px] text-slate-400 mt-0.5 block">Official form sequential number</span>
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Fiscal Year (CY): <span className="text-rose-600">*</span>
                        </label>
                        <input
                            type="text"
                            required
                            value={fiscalYear}
                            onChange={(e) => setFiscalYear(e.target.value)}
                            className="w-full text-xs p-2.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="2026"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Plan Type: <span className="text-rose-600">*</span>
                        </label>
                        <div className="flex items-center gap-6 mt-2">
                            <label className="inline-flex items-center gap-2 text-xs font-semibold cursor-pointer">
                                <input
                                    type="radio"
                                    name="plan_type"
                                    value="INDICATIVE"
                                    checked={planType === 'INDICATIVE'}
                                    onChange={(e) => setPlanType(e.target.value)}
                                    className="text-blue-600 h-4 w-4"
                                />
                                INDICATIVE
                            </label>
                            <label className="inline-flex items-center gap-2 text-xs font-semibold cursor-pointer">
                                <input
                                    type="radio"
                                    name="plan_type"
                                    value="FINAL"
                                    checked={planType === 'FINAL'}
                                    onChange={(e) => setPlanType(e.target.value)}
                                    className="text-blue-600 h-4 w-4"
                                />
                                FINAL
                            </label>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Implementing Office / Unit:
                        </label>
                        <input
                            type="text"
                            required
                            value={implementingUnit}
                            onChange={(e) => setImplementingUnit(e.target.value)}
                            placeholder="e.g. Office of the Provincial Procurement Management Officer"
                            className="w-full text-xs p-2.5 bg-white border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none font-medium text-slate-900"
                        />
                    </div>
                </div>

                <div className="mb-5">
                    <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Project Procurement Title / General Purpose: <span className="text-rose-600">*</span>
                    </label>
                    <input
                        type="text"
                        required
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder=""
                        className="w-full text-xs p-2.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none font-semibold text-slate-900"
                    />
                </div>

                {/* Delivery Specifications & Terms */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-slate-200">
                    <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1">Delivery Period:</label>
                        <input
                            type="text"
                            value={deliveryPeriod}
                            onChange={(e) => setDeliveryPeriod(e.target.value)}
                            placeholder="e.g. 30 Calendar Days upon receipt of PO"
                            className="w-full text-xs p-2 border border-slate-300 rounded-md"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1">Place of Delivery:</label>
                        <input
                            type="text"
                            value={placeOfDelivery}
                            onChange={(e) => setPlaceOfDelivery(e.target.value)}
                            placeholder="e.g. PGSO Warehouse - onsite"
                            className="w-full text-xs p-2 border border-slate-300 rounded-md"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1">Payment Method:</label>
                        <input
                            type="text"
                            value={paymentMethod}
                            onChange={(e) => setPaymentMethod(e.target.value)}
                            placeholder="e.g. One-time Payment - Credit basis"
                            className="w-full text-xs p-2 border border-slate-300 rounded-md"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1">
                            Other Terms: <span className="text-slate-400 font-normal text-[11px]">(Optional)</span>
                        </label>
                        <textarea
                            rows={2}
                            value={warrantyAndOtherTerms}
                            onChange={(e) => setWarrantyAndOtherTerms(e.target.value)}
                            placeholder="e.g. Green Specefication or Warranty Terms"
                            className="w-full text-xs p-2 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none leading-snug resize-y"
                        />
                    </div>
                </div>
            </div>

            {/* PPMP Items Table */}
            <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
                <div className="flex items-center justify-between mb-2">
                    <h3 className="text-sm font-bold uppercase text-slate-800 tracking-wide">
                        Procurement Plan Items & Schedule
                    </h3>
                    <span className="text-xs text-slate-500">
                        Total {items.length} item {items.length === 1 ? 'row' : 'rows'}
                    </span>
                </div>

                <ProcurementPlanTable
                    items={items}
                    isEditable={true}
                    onAddItem={handleAddItem}
                    onRemoveItem={handleRemoveItem}
                    onUpdateItem={handleUpdateItem}
                    totalBudget={totalBudget}
                    accountCode={accountCode}
                    onAccountCodeChange={setAccountCode}
                />
            </div>
        </form>
    );
};
