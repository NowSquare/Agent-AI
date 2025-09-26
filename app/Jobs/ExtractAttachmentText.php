<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ExtractAttachmentText implements ShouldQueue
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
        Log::info('Job start: ExtractAttachmentText', [
            'attachment_id' => $this->attachmentId,
            'queue' => method_exists($this->job ?? null, 'getQueue') ? $this->job->getQueue() : null,
            'connection' => method_exists($this->job ?? null, 'getConnectionName') ? $this->job->getConnectionName() : null,
            'attempts' => method_exists($this->job ?? null, 'attempts') ? $this->job->attempts() : null,
        ]);
        $attachment = Attachment::find($this->attachmentId);

        if (! $attachment) {
            Log::error('Attachment not found for text extraction', [
                'attachment_id' => $this->attachmentId,
            ]);

            return;
        }

        if (! $attachment->isClean()) {
            Log::warning('Skipping text extraction for non-clean attachment', [
                'attachment_id' => $attachment->id,
                'scan_status' => $attachment->scan_status,
            ]);

            return;
        }

        Log::info('Starting attachment text extraction', [
            'attachment_id' => $attachment->id,
            'filename' => $attachment->filename,
            'mime' => $attachment->mime,
        ]);

        Log::debug('ExtractAttachmentText: calling extractText()', [
            'attachment_id' => $attachment->id,
        ]);
        $extracted = $attachmentService->extractText($attachment);
        Log::debug('ExtractAttachmentText: extractText() returned', [
            'attachment_id' => $attachment->id,
            'result' => $extracted,
        ]);

        if ($extracted) {
            // Dispatch next job in chain: summarize (use configured queue)
            $attachmentsQueue = config('attachments.processing.queue', 'attachments');
            SummarizeAttachment::dispatch($attachment->id)->onQueue($attachmentsQueue);
        } else {
            Log::warning('Attachment text extraction failed, stopping summarization', [
                'attachment_id' => $attachment->id,
                'extract_status' => $attachment->extract_status,
            ]);
        }
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::info('Job end: ExtractAttachmentText', [
            'attachment_id' => $this->attachmentId,
            'duration_ms' => $durationMs,
        ]);
    }
}
