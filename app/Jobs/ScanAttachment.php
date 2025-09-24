<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScanAttachment implements ShouldQueue
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
            Log::error('Attachment not found for scanning', [
                'attachment_id' => $this->attachmentId,
            ]);

            return;
        }

        Log::info('Starting attachment scan', [
            'attachment_id' => $attachment->id,
            'filename' => $attachment->filename,
            'size_bytes' => $attachment->size_bytes,
        ]);

        $isClean = $attachmentService->scan($attachment);

        if ($isClean) {
            // Dispatch next job in chain: extract text
            ExtractAttachmentText::dispatch($attachment->id)->onQueue('attachments');
        } else {
            Log::warning('Attachment scan found infection, stopping processing chain', [
                'attachment_id' => $attachment->id,
                'scan_result' => $attachment->scan_result,
            ]);
        }
    }
}
