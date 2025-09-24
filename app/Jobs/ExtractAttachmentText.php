<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExtractAttachmentText implements ShouldQueue
{
    use Queueable;

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

        $extracted = $attachmentService->extractText($attachment);

        if ($extracted) {
            // Dispatch next job in chain: summarize
            SummarizeAttachment::dispatch($attachment->id)->onQueue('attachments');
        } else {
            Log::warning('Attachment text extraction failed, stopping summarization', [
                'attachment_id' => $attachment->id,
                'extract_status' => $attachment->extract_status,
            ]);
        }
    }
}
