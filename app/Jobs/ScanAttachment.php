<?php

namespace App\Jobs;

use App\Models\Action;
use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ScanAttachment implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $attachmentId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AttachmentService $attachmentService): void
    {
        $startedAt = microtime(true);
        $attachment = Attachment::find($this->attachmentId);

        if (! $attachment) {
            Log::error('Attachment not found for scanning', [
                'attachment_id' => $this->attachmentId,
            ]);

            return;
        }

        Log::info('Job start: ScanAttachment', [
            'attachment_id' => $attachment->id,
            'queue' => method_exists($this->job ?? null, 'getQueue') ? $this->job->getQueue() : null,
            'connection' => method_exists($this->job ?? null, 'getConnectionName') ? $this->job->getConnectionName() : null,
            'attempts' => method_exists($this->job ?? null, 'attempts') ? $this->job->attempts() : null,
        ]);
        Log::info('Starting attachment scan', [
            'attachment_id' => $attachment->id,
            'filename' => $attachment->filename,
            'size_bytes' => $attachment->size_bytes,
        ]);

        Log::debug('ScanAttachment: calling scan()', [
            'attachment_id' => $attachment->id,
        ]);
        $isClean = $attachmentService->scan($attachment);
        Log::debug('ScanAttachment: scan() returned', [
            'attachment_id' => $attachment->id,
            'result' => $isClean,
            'scan_status' => $attachment->scan_status,
        ]);

        // Always continue processing chain for text extraction, regardless of scan result.
        // We want to extract text even from infected files for analysis (safely, in isolation).
        // The infected attachment notification will be handled later when all attachments are processed.
        $attachmentsQueue = config('attachments.processing.queue', 'attachments');
        ExtractAttachmentText::dispatch($attachment->id)->onQueue($attachmentsQueue);

        if (!$isClean) {
            Log::warning('Attachment scan found infection, but continuing processing chain', [
                'attachment_id' => $attachment->id,
                'scan_result' => $attachment->scan_result,
            ]);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::info('Job end: ScanAttachment', [
            'attachment_id' => $this->attachmentId,
            'duration_ms' => $durationMs,
        ]);
    }
}
