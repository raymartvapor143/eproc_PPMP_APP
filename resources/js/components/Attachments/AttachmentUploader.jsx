import React, { useState } from 'react';
import { ppmpService } from '../../services/api';
import { formatDate } from '../UI/StatusBadge';
import { Paperclip, UploadCloud, FileText, Image as ImageIcon, Download, Trash2, Eye, Loader2, History } from 'lucide-react';

export const AttachmentUploader = ({
    ppmp,
    historicalPpmps = [],
    canUpload = false,
    canDelete = false,
    onAttachmentChanged,
}) => {
    const [uploading, setUploading] = useState(false);
    const [deletingId, setDeletingId] = useState(null);
    const [error, setError] = useState(null);
    const currentAttachments = ppmp?.attachments || [];

    // Collect attachments from earlier / parent / baseline PPMPs (e.g. Supplemental, Amended, Annual)
    const historicalAttachments = React.useMemo(() => {
        const list = [];
        const seenUuids = new Set(currentAttachments.map(a => a.uuid || a.id));

        (historicalPpmps || []).forEach((histPpmp) => {
            const atts = histPpmp?.attachments || [];
            atts.forEach((att) => {
                const key = att.uuid || att.id;
                if (!seenUuids.has(key)) {
                    seenUuids.add(key);
                    list.push({
                        ...att,
                        _sourcePpmp: histPpmp,
                    });
                }
            });
        });
        return list;
    }, [currentAttachments, historicalPpmps]);

    const totalFilesCount = currentAttachments.length + historicalAttachments.length;

    const isImageFile = (mimeType, filename) => {
        if (mimeType && mimeType.startsWith('image/')) return true;
        if (filename && /\.(jpe?g|png|webp|gif)$/i.test(filename)) return true;
        return false;
    };

    const handleFileChange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        const isPdfOrImage = allowedTypes.includes(file.type) || /\.(pdf|jpe?g|png|webp)$/i.test(file.name);

        if (!isPdfOrImage) {
            setError('Only PDF documents and image files (JPG, PNG, WebP) are allowed.');
            return;
        }

        if (file.size > 20 * 1024 * 1024) {
            setError('File size exceeds the 20MB maximum limit.');
            return;
        }

        setError(null);
        setUploading(true);

        const formData = new FormData();
        formData.append('file', file);

        try {
            await ppmpService.uploadAttachment(ppmp.uuid, formData);
            if (onAttachmentChanged) onAttachmentChanged();
            e.target.value = null;
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to upload attachment.');
        } finally {
            setUploading(false);
        }
    };

    const handleDelete = async (attachmentUuid) => {
        if (!window.confirm('Are you sure you want to remove this attachment?')) return;

        setDeletingId(attachmentUuid);
        try {
            await ppmpService.deleteAttachment(ppmp.uuid, attachmentUuid);
            if (onAttachmentChanged) onAttachmentChanged();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to delete attachment.');
        } finally {
            setDeletingId(null);
        }
    };

    return (
        <div className="bg-white border border-slate-200 rounded-lg p-4 shadow-xs">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                <div className="flex items-center gap-2">
                    <Paperclip className="w-4 h-4 text-blue-600" />
                    <h3 className="font-bold text-xs uppercase tracking-wider text-slate-800">
                        Supporting Documents &amp; Requirements
                    </h3>
                </div>
                <span className="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">
                    {totalFilesCount} file{totalFilesCount !== 1 ? 's' : ''}
                </span>
            </div>

            {error && (
                <div className="mb-3 p-2 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded">
                    {error}
                </div>
            )}

            {/* List of Attachments */}
            {totalFilesCount === 0 ? (
                <div className="text-center py-4 bg-slate-50 rounded border border-dashed border-slate-200 text-slate-500 text-xs">
                    No attachments uploaded yet.
                </div>
            ) : (
                <div className="space-y-3">
                    {/* Current PPMP Files */}
                    {currentAttachments.length > 0 && (
                        <div>
                            {historicalAttachments.length > 0 && (
                                <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                    <span>Current PPMP Documents</span>
                                    <span className="text-blue-600">({currentAttachments.length})</span>
                                </div>
                            )}
                            <ul className="space-y-2">
                                {currentAttachments.map((att) => {
                                    const isImg = isImageFile(att.mime_type, att.original_filename);
                                    const fileKey = att.encrypted_uuid || att.uuid;
                                    const isDeleting = deletingId === fileKey;

                                    return (
                                        <li
                                            key={att.uuid || att.id}
                                            className="flex items-center justify-between p-2.5 bg-slate-50 hover:bg-slate-100/80 border border-slate-200 rounded-md transition"
                                        >
                                            <div className="flex items-center gap-2.5 overflow-hidden">
                                                <div className="w-8 h-8 rounded bg-white border border-slate-200 flex items-center justify-center text-slate-500 shrink-0">
                                                    {isImg ? (
                                                        <ImageIcon className="w-4 h-4 text-emerald-600" />
                                                    ) : (
                                                        <FileText className="w-4 h-4 text-rose-600" />
                                                    )}
                                                </div>
                                                <div className="truncate">
                                                    <div className="text-xs font-semibold text-slate-800 truncate" title={att.original_filename}>
                                                        {att.original_filename}
                                                    </div>
                                                    <div className="text-[10px] text-slate-500">
                                                        {(att.file_size / 1024).toFixed(1)} KB &bull; {isImg ? 'Image' : 'PDF Document'} &bull; Uploaded {formatDate(att.created_at)}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-1.5 shrink-0">
                                                <a
                                                    href={ppmpService.getAttachmentViewUrl(ppmp.uuid, att)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded"
                                                    title="View securely in browser"
                                                >
                                                    <Eye className="w-4 h-4" />
                                                </a>

                                                <a
                                                    href={ppmpService.getAttachmentDownloadUrl(ppmp.uuid, att)}
                                                    download
                                                    className="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded"
                                                    title="Download file"
                                                >
                                                    <Download className="w-4 h-4" />
                                                </a>

                                                {canDelete && (
                                                    <button
                                                        type="button"
                                                        disabled={isDeleting}
                                                        onClick={() => handleDelete(fileKey)}
                                                        className="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded cursor-pointer disabled:opacity-50"
                                                        title="Delete file"
                                                    >
                                                        {isDeleting ? (
                                                            <Loader2 className="w-4 h-4 animate-spin" />
                                                        ) : (
                                                            <Trash2 className="w-4 h-4" />
                                                        )}
                                                    </button>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    {/* Historical / Baseline / Supplemental PPMP Attachments */}
                    {historicalAttachments.length > 0 && (
                        <div className={currentAttachments.length > 0 ? "pt-2 border-t border-slate-200" : ""}>
                            <div className="text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                                <div className="flex items-center gap-1">
                                    <History className="w-3.5 h-3.5 text-indigo-600" />
                                    <span>From Previous Revisions &amp; Baseline</span>
                                    <span className="text-indigo-600">({historicalAttachments.length})</span>
                                </div>
                                <span className="text-[9px] text-slate-400 normal-case">Preserved baseline documents</span>
                            </div>
                            <ul className="space-y-2">
                                {historicalAttachments.map((att) => {
                                    const isImg = isImageFile(att.mime_type, att.original_filename);
                                    const sourcePpmp = att._sourcePpmp;
                                    const sourceUuid = sourcePpmp?.uuid || ppmp.uuid;
                                    const sourceLabel = !sourcePpmp?.parent_id
                                        ? 'Annual Baseline'
                                        : (sourcePpmp?.amendment_type === 'AMENDMENT' ? 'Amended Baseline' : 'Supplemental Baseline');

                                    return (
                                        <li
                                            key={`hist_${att.uuid || att.id}`}
                                            className="flex items-center justify-between p-2.5 bg-indigo-50/50 hover:bg-indigo-50 border border-indigo-200/80 rounded-md transition"
                                        >
                                            <div className="flex items-center gap-2.5 overflow-hidden">
                                                <div className="w-8 h-8 rounded bg-white border border-indigo-200 flex items-center justify-center text-indigo-700 shrink-0">
                                                    {isImg ? (
                                                        <ImageIcon className="w-4 h-4 text-emerald-600" />
                                                    ) : (
                                                        <FileText className="w-4 h-4 text-rose-600" />
                                                    )}
                                                </div>
                                                <div className="truncate">
                                                    <div className="flex items-center gap-1.5 flex-wrap">
                                                        <span className="text-xs font-semibold text-slate-900 truncate" title={att.original_filename}>
                                                            {att.original_filename}
                                                        </span>
                                                        <span className="text-[9px] font-bold px-1.5 py-0.2 bg-indigo-100 text-indigo-800 rounded border border-indigo-200">
                                                            PPMP No. {sourcePpmp?.ppmp_number} &bull; {sourceLabel}
                                                        </span>
                                                    </div>
                                                    <div className="text-[10px] text-slate-500">
                                                        {(att.file_size / 1024).toFixed(1)} KB &bull; {isImg ? 'Image' : 'PDF Document'} &bull; Uploaded {formatDate(att.created_at)}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-1.5 shrink-0">
                                                <a
                                                    href={ppmpService.getAttachmentViewUrl(sourceUuid, att)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="p-1.5 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-100 rounded"
                                                    title="View historical document in new tab"
                                                >
                                                    <Eye className="w-4 h-4" />
                                                </a>

                                                <a
                                                    href={ppmpService.getAttachmentDownloadUrl(sourceUuid, att)}
                                                    download
                                                    className="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-100 rounded"
                                                    title="Download file"
                                                >
                                                    <Download className="w-4 h-4" />
                                                </a>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {/* Secure Uploader Form */}
            {canUpload && (
                <div className="mt-3">
                    <label className="flex flex-col items-center justify-center p-4 border-2 border-dashed border-blue-200 hover:border-blue-400 bg-blue-50/50 rounded-lg cursor-pointer transition">
                        <UploadCloud className="w-6 h-6 text-blue-600 mb-1" />
                        <span className="text-xs font-semibold text-blue-900">
                            {uploading ? 'Uploading attachment securely...' : 'Click or Drag PDF or Picture (JPG, PNG) here'}
                        </span>
                        <span className="text-[10px] text-slate-500 mt-0.5">
                            PDF or Images up to 20MB • Stored in private non-public storage
                        </span>
                        <input
                            type="file"
                            accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            onChange={handleFileChange}
                            disabled={uploading}
                            className="hidden"
                        />
                    </label>
                </div>
            )}
        </div>
    );
};
