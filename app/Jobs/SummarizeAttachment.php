<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SummarizeAttachment implements ShouldQueue
{
    use Queueable;

    public string $queue = 'attachments';

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
            Log::error('Attachment not found for summarization', [
                'attachment_id' => $this->attachmentId,
            ]);

            return;
        }

        if (! $attachment->isExtracted()) {
            Log::warning('Skipping summarization for non-extracted attachment', [
                'attachment_id' => $attachment->id,
                'extract_status' => $attachment->extract_status,
            ]);

            return;
        }

        Log::info('Starting attachment summarization', [
            'attachment_id' => $attachment->id,
            'filename' => $attachment->filename,
            'mime' => $attachment->mime,
        ]);

        $summarized = $attachmentService->summarize($attachment);

        if ($summarized) {
            Log::info('Attachment summarization completed successfully', [
                'attachment_id' => $attachment->id,
                'has_gist' => ! empty($attachment->summarize_json['gist']),
                'key_points_count' => count($attachment->summarize_json['key_points'] ?? []),
            ]);
        } else {
            Log::warning('Attachment summarization failed', [
                'attachment_id' => $attachment->id,
            ]);
        }
    }
}
