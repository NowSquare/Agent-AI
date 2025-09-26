<?php

namespace App\Jobs;

use App\Models\Action;
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
            // Dispatch next job in chain: extract text (use configured queue)
            $attachmentsQueue = config('attachments.processing.queue', 'attachments');
            ExtractAttachmentText::dispatch($attachment->id)->onQueue($attachmentsQueue);
        } else {
            Log::warning('Attachment scan found infection, stopping processing chain', [
                'attachment_id' => $attachment->id,
                'scan_result' => $attachment->scan_result,
            ]);

            // Ensure the user receives an incident response email.
            // If no outbound message has been sent on this thread yet, dispatch SendActionResponse
            // using the latest action for the thread.
            $thread = $attachment->emailMessage?->thread;
            if ($thread && ! $thread->emailMessages()->where('direction', 'outbound')->exists()) {
                $action = Action::where('thread_id', $thread->id)
                    ->latest('created_at')
                    ->first();

                if ($action) {
                    SendActionResponse::dispatch($action);
                }
            }
        }
    }
}
