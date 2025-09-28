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

        // Check if all attachments are fully processed (scanned and extracted), then route accordingly
        $email = $attachment->emailMessage;
        if ($email) {
            $all = $email->attachments()->get();
            
            // Check if all attachments are fully processed (scanned + extracted)
            $allProcessed = $all->every(function ($att) {
                $scanDone = in_array($att->scan_status, ['clean', 'infected', 'skipped']);
                $extractDone = $att->extract_status === 'done';
                return $scanDone && $extractDone;
            });

            if ($allProcessed && $email->processing_status === 'awaiting_attachments') {
                // Check for infected attachments first
                $infectedAttachments = $all->where('scan_status', 'infected');
                
                if ($infectedAttachments->isNotEmpty()) {
                    // Send infected attachments notification instead of normal processing
                    Log::info('Infected attachments detected, dispatching security notification', [
                        'email_message_id' => $email->id,
                        'infected_count' => $infectedAttachments->count(),
                        'infected_files' => $infectedAttachments->pluck('filename')->toArray(),
                    ]);
                    
                    $email->update(['processing_status' => 'processing']);
                    SendInfectedAttachmentsNotification::dispatch($email->id)
                        ->onQueue(config('attachments.processing.queue', 'default'));
                } else {
                    // All attachments are processed (scanned + extracted), proceed with LLM processing
                    // Note: Some attachments may have failed extraction/summarization, but we proceed with whatever content we have
                    Log::info('All attachments processed, dispatching deferred LLM processing', [
                        'email_message_id' => $email->id,
                        'attachments_with_content' => $all->filter(function ($att) {
                            $hasSummary = ! empty($att->summarize_json['gist'] ?? null);
                            $hasRawExcerpt = ! empty($att->extract_result_json['excerpt'] ?? '');
                            return $hasSummary || $hasRawExcerpt;
                        })->count(),
                        'total_attachments' => $all->count(),
                    ]);
                    
                    $email->update(['processing_status' => 'processing']);
                    \App\Jobs\ProcessEmailAfterAttachments::dispatch($email->id)
                        ->onQueue(config('attachments.processing.queue', 'default'));
                }
            }
        }
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::info('Job end: SummarizeAttachment', [
            'attachment_id' => $this->attachmentId,
            'duration_ms' => $durationMs,
        ]);
    }
}
