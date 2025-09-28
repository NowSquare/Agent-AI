<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Models\Action;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification email when infected attachments are detected.
 * This job is triggered after all attachments for an email have been scanned.
 */
class SendInfectedAttachmentsNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public string $emailMessageId
    ) {}

    public function handle(): void
    {
        $emailMessage = EmailMessage::find($this->emailMessageId);
        
        if (!$emailMessage) {
            Log::error('EmailMessage not found for infected attachments notification', [
                'email_message_id' => $this->emailMessageId,
            ]);
            return;
        }

        $thread = $emailMessage->thread;
        $account = $thread?->account;
        
        if (!$thread || !$account) {
            Log::error('Missing thread or account for infected notification', [
                'email_message_id' => $emailMessage->id,
            ]);
            return;
        }

        // Get all infected attachments
        $infectedAttachments = $emailMessage->attachments()
            ->where('scan_status', 'infected')
            ->get();

        if ($infectedAttachments->isEmpty()) {
            Log::info('No infected attachments found, skipping notification', [
                'email_message_id' => $emailMessage->id,
            ]);
            return;
        }

        // Create action for infected attachments response
        $infectedFiles = $infectedAttachments->pluck('filename')->toArray();
        $infectedDetails = $infectedAttachments->map(function ($attachment) {
            return [
                'filename' => $attachment->filename,
                'threat' => $attachment->scan_result,
                'size' => $attachment->size_bytes,
            ];
        })->toArray();

        $action = Action::create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'email_message_id' => $emailMessage->id,
            'type' => 'infected_attachments',
            'payload_json' => [
                'infected_files' => $infectedFiles,
                'infected_details' => $infectedDetails,
                'total_attachments' => $emailMessage->attachments()->count(),
                'clean_attachments' => $emailMessage->attachments()->whereIn('scan_status', ['clean', 'skipped'])->count(),
            ],
            'status' => 'pending',
            'confidence' => 1.0, // High confidence for security notifications
        ]);

        Log::info('Created infected attachments action', [
            'email_message_id' => $emailMessage->id,
            'action_id' => $action->id,
            'infected_count' => $infectedAttachments->count(),
            'infected_files' => $infectedFiles,
        ]);

        // Dispatch the action response immediately (high priority security notification)
        SendActionResponse::dispatch($action);

        // Update email processing status to prevent normal LLM processing
        $emailMessage->update([
            'processing_status' => 'blocked_infected',
            'processed_at' => now(),
        ]);

        Log::info('Infected attachments notification dispatched', [
            'email_message_id' => $emailMessage->id,
            'action_id' => $action->id,
        ]);
    }
}
