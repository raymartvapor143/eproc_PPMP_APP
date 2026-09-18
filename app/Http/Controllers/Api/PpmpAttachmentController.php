<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ppmp;
use App\Models\PpmpAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PpmpAttachmentController extends Controller
{
    /**
     * Upload supporting PDF document (End user or allowed reviewer)
     * Stores in private non-public storage: storage/app/private/ppmp/
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();

        // Check if user has attachment upload permission:
        // STRICT RULE: Only the End-User (creator) can attach supporting files.
        // Reviewers (Head, Budget Officer, OPPMO, TWG) cannot attach files.
        $isCreator = $ppmp->created_by === $user->id;
        $isBeforeFormalReview = in_array($ppmp->status, [
            'DRAFT',
            'HEAD_PENDING',
            'HEAD_APPROVED',
            'HEAD_RETURNED',
            'BUDGET_OFFICER_RETURNED',
            'OPPMO_RETURNED',
            'TWG_RETURNED',
        ]);

        $canUpload = ($isCreator && $isBeforeFormalReview) || $user->isAdmin();

        if (!$canUpload) {
            return response()->json([
                'message' => 'Only the end-user (creator) can attach documents or pictures to this PPMP.'
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:20480', // max 20MB
        ]);

        $uploadedFile = $request->file('file');
        $mime = $uploadedFile->getMimeType();
        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (!in_array($mime, $allowedMimes)) {
            return response()->json(['message' => 'Only PDF documents or image files (JPG, PNG, WebP) are allowed.'], 422);
        }

        // Verify PDF Magic Bytes if PDF
        if ($mime === 'application/pdf') {
            $handle = fopen($uploadedFile->getRealPath(), 'rb');
            $header = fread($handle, 5);
            fclose($handle);
            if ($header !== '%PDF-') {
                return response()->json(['message' => 'Invalid file content. Must be a valid PDF document.'], 422);
            }
            $fileExt = 'pdf';
        } else {
            $fileExt = in_array($extension, ['jpg', 'jpeg', 'png', 'webp']) ? $extension : 'png';
        }

        // Generate safe internal filename
        $internalFilename = (string) Str::uuid() . '.' . $fileExt;
        $directory = 'ppmp/' . $ppmp->id;
        
        // Store in private disk (storage/app/private/)
        $path = $uploadedFile->storeAs($directory, $internalFilename, 'local');

        $attachment = PpmpAttachment::create([
            'uuid' => (string) Str::uuid(),
            'ppmp_id' => $ppmp->id,
            'uploaded_by' => $user->id,
            'original_filename' => basename($uploadedFile->getClientOriginalName()),
            'stored_filename' => $internalFilename,
            'storage_path' => $path,
            'mime_type' => $mime,
            'file_size' => $uploadedFile->getSize(),
        ]);

        AuditLog::log('ATTACHMENT_UPLOADED', 'ppmp_attachments', $attachment->id, null, [
            'filename' => $attachment->original_filename,
            'size' => $attachment->file_size
        ], $user->id);

        return response()->json([
            'message' => 'PDF attachment uploaded securely.',
            'attachment' => $attachment->load('uploader'),
        ], 201);
    }

    /**
     * Download attachment securely
     */
    public function download(Request $request, string $uuid, string $attachmentUuid): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();
        $resolvedUuid = PpmpAttachment::decryptUuid($attachmentUuid);

        // Allow attachment lookup directly on current PPMP or any ancestor PPMP in the same tracking family
        // Guarded with cycle detection and depth limit
        $ancestorIds = [];
        $curr = $ppmp;
        $maxDepth = 25;
        $depth = 0;
        while ($curr && $depth < $maxDepth) {
            if (in_array($curr->id, $ancestorIds, true)) {
                break;
            }
            $ancestorIds[] = $curr->id;
            $depth++;
            $curr = $curr->parent;
        }

        $attachment = PpmpAttachment::whereIn('ppmp_id', $ancestorIds)
            ->where(function ($q) use ($resolvedUuid, $attachmentUuid) {
                $q->where('uuid', $resolvedUuid)
                  ->orWhere('uuid', $attachmentUuid);
            })
            ->firstOrFail();

        $this->authorizeAttachmentAccess($user, $ppmp, $attachment);

        if (!Storage::disk('local')->exists($attachment->storage_path)) {
            return response()->json(['message' => 'Attachment file not found on disk.'], 404);
        }

        $fullPath = Storage::disk('local')->path($attachment->storage_path);

        AuditLog::log('ATTACHMENT_DOWNLOADED', 'ppmp_attachments', $attachment->id, null, [
            'filename' => $attachment->original_filename
        ], $user->id);

        return response()->download($fullPath, $attachment->original_filename, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * View/Stream attachment securely in browser
     */
    public function view(Request $request, string $uuid, string $attachmentUuid): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();
        $resolvedUuid = PpmpAttachment::decryptUuid($attachmentUuid);

        // Allow attachment lookup directly on current PPMP or any ancestor PPMP in the same tracking family
        // Guarded with cycle detection and depth limit
        $ancestorIds = [];
        $curr = $ppmp;
        $maxDepth = 25;
        $depth = 0;
        while ($curr && $depth < $maxDepth) {
            if (in_array($curr->id, $ancestorIds, true)) {
                break;
            }
            $ancestorIds[] = $curr->id;
            $depth++;
            $curr = $curr->parent;
        }

        $attachment = PpmpAttachment::whereIn('ppmp_id', $ancestorIds)
            ->where(function ($q) use ($resolvedUuid, $attachmentUuid) {
                $q->where('uuid', $resolvedUuid)
                  ->orWhere('uuid', $attachmentUuid);
            })
            ->firstOrFail();

        $this->authorizeAttachmentAccess($user, $ppmp, $attachment);

        if (!Storage::disk('local')->exists($attachment->storage_path)) {
            return response()->json(['message' => 'Attachment file not found on disk.'], 404);
        }

        $fullPath = Storage::disk('local')->path($attachment->storage_path);

        return response()->file($fullPath, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($attachment->original_filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Delete attachment (Creator or Admin while editable)
     */
    public function destroy(Request $request, string $uuid, string $attachmentUuid): JsonResponse
    {
        $user = $request->user();
        $ppmp = Ppmp::where('uuid', $uuid)->firstOrFail();
        $resolvedUuid = PpmpAttachment::decryptUuid($attachmentUuid);

        $attachment = PpmpAttachment::where('ppmp_id', $ppmp->id)
            ->where(function ($q) use ($resolvedUuid, $attachmentUuid) {
                $q->where('uuid', $resolvedUuid)
                  ->orWhere('uuid', $attachmentUuid);
            })
            ->firstOrFail();

        $isCreator = $ppmp->created_by === $user->id;
        $isBeforeFormalReview = in_array($ppmp->status, [
            'DRAFT',
            'HEAD_PENDING',
            'HEAD_APPROVED',
            'HEAD_RETURNED',
            'BUDGET_OFFICER_RETURNED',
            'OPPMO_RETURNED',
            'TWG_RETURNED',
        ]);

        $canDelete = ($isCreator && $isBeforeFormalReview) || $user->isAdmin();

        if (!$canDelete) {
            return response()->json(['message' => 'You cannot remove attachments from this PPMP in its current status.'], 403);
        }

        if (Storage::disk('local')->exists($attachment->storage_path)) {
            Storage::disk('local')->delete($attachment->storage_path);
        }

        $filename = $attachment->original_filename;
        $attachmentId = $attachment->id;
        $attachment->delete();

        AuditLog::log('ATTACHMENT_DELETED', 'ppmp_attachments', $attachmentId, ['filename' => $filename], null, $user->id);

        return response()->json(['message' => 'Attachment removed successfully.']);
    }

    /**
     * Enforce strict role-based and office-based authorization
     */
    private function authorizeAttachmentAccess(User $user, Ppmp $ppmp, ?PpmpAttachment $attachment = null): void
    {
        if ($user->isAdmin()) {
            return;
        }

        // Request letters are accessible to any reviewer or admin evaluating the request
        if ($attachment && $attachment->attachment_type === 'REQUEST_LETTER') {
            if ($user->isAdmin() || $user->isBudgetOfficer() || $user->isOppmo() || $user->isTwg() || $user->id === $ppmp->created_by) {
                return;
            }
        }

        // Reviewers (Budget, OPPMO, TWG) have access once it enters review workflow or when checking ready to print
        if ($user->isBudgetOfficer() || $user->isOppmo() || $user->isTwg()) {
            if (!in_array($ppmp->status, ['DRAFT', 'HEAD_PENDING', 'HEAD_RETURNED'])) {
                return;
            }
        }

        // Office Head has access to their office's PPMPs
        if ($user->isHead() && $user->office_id === $ppmp->office_id) {
            return;
        }

        // Creator has access
        if ($user->id === $ppmp->created_by) {
            return;
        }

        abort(403, 'Unauthorized. You do not have permission to access attachments for this PPMP.');
    }
}
