<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    /**
     * Download an attachment via signed URL
     */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        // Validate that the request is properly signed
        if (! $request->hasValidSignature()) {
            Log::warning('Invalid signature for attachment download', [
                'attachment_id' => $attachment->id,
                'ip' => $request->ip(),
            ]);

            abort(403, 'Invalid or expired download link');
        }

        // Check if attachment can be downloaded
        if (! $attachment->canDownload()) {
            Log::warning('Attachment download denied', [
                'attachment_id' => $attachment->id,
                'scan_status' => $attachment->scan_status,
                'has_storage_path' => ! empty($attachment->storage_path),
                'ip' => $request->ip(),
            ]);

            abort(403, 'Attachment not available for download');
        }

        // Verify nonce if present in meta_json
        if (! empty($attachment->meta_json['nonce'])) {
            $expectedNonce = $attachment->meta_json['nonce'];
            $providedNonce = $request->query('nonce');

            if ($providedNonce !== $expectedNonce) {
                Log::warning('Invalid nonce for attachment download', [
                    'attachment_id' => $attachment->id,
                    'expected_nonce' => $expectedNonce,
                    'provided_nonce' => $providedNonce,
                    'ip' => $request->ip(),
                ]);

                abort(403, 'Invalid download link');
            }
        }

        $filePath = $attachment->storage_path;
        $disk = $attachment->storage_disk;

        // Check if file exists
        if (! Storage::disk($disk)->exists($filePath)) {
            Log::error('Attachment file not found on disk', [
                'attachment_id' => $attachment->id,
                'storage_path' => $filePath,
                'disk' => $disk,
            ]);

            abort(404, 'File not found');
        }

        Log::info('Serving attachment download', [
            'attachment_id' => $attachment->id,
            'filename' => $attachment->filename,
            'size_bytes' => $attachment->size_bytes,
            'ip' => $request->ip(),
        ]);

        // Stream the file with proper headers
        return Storage::disk($disk)->download($filePath, $attachment->filename, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'attachment; filename="'.$attachment->filename.'"',
            'Cache-Control' => 'private, no-cache',
        ]);
    }
}
