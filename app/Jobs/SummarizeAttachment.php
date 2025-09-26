<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Models\EmailMessage;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SummarizeAttachment implements ShouldQueue
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
        Log::info('Job start: SummarizeAttachment', [
            'attachment_id' => $this->attachmentId,
            'queue' => method_exists($this->job ?? null, 'getQueue') ? $this->job->getQueue() : null,
            'connection' => method_exists($this->job ?? null, 'getConnectionName') ? $this->job->getConnectionName() : null,
            'attempts' => method_exists($this->job ?? null, 'attempts') ? $this->job->attempts() : null,
        ]);
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

        Log::debug('SummarizeAttachment: calling summarize()', [
            'attachment_id' => $attachment->id,
        ]);
        $summarized = $attachmentService->summarize($attachment);
        Log::debug('SummarizeAttachment: summarize() returned', [
            'attachment_id' => $attachment->id,
            'result' => $summarized,
        ]);

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

        // If all attachments now have at least a raw excerpt or summary, run LLM processing for the email
        $email = $attachment->emailMessage;
        if ($email) {
            $all = $email->attachments()->get();
            $allReady = $all->every(function ($att) {
                $hasSummary = ! empty($att->summarize_json['gist'] ?? null);
                $hasRawExcerpt = ! empty($att->extract_result_json['excerpt'] ?? '');
                return $att->extract_status === 'done' && ($hasSummary || $hasRawExcerpt);
            });
            if ($allReady && $email->processing_status === 'awaiting_attachments') {
                Log::info('All attachments ready, dispatching deferred LLM processing', [
                    'email_message_id' => $email->id,
                ]);
                // Flip status to processing to avoid double enqueue from multiple summarize completions
                $email->update(['processing_status' => 'processing']);
                // Dispatch on the same configured processing queue used for attachments (defaults to 'default')
                \App\Jobs\ProcessEmailAfterAttachments::dispatch($email->id)
                    ->onQueue(config('attachments.processing.queue', 'default'));
            }
        }
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::info('Job end: SummarizeAttachment', [
            'attachment_id' => $this->attachmentId,
            'duration_ms' => $durationMs,
        ]);
    }
}
