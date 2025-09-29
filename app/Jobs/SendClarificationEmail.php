<?php

namespace App\Jobs;

use App\Mail\ActionClarificationMail;
use App\Models\Action;
use App\Models\Thread;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendClarificationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Action $action,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Sending clarification email', [
            'action_id' => $this->action->id,
            'action_type' => $this->action->type,
        ]);

        // Check idempotence: don't send if already sent
        if ($this->action->meta_json['clarification_sent_at'] ?? false) {
            Log::info('Clarification email already sent, skipping', [
                'action_id' => $this->action->id,
            ]);

            return;
        }

        $thread = $this->action->thread;
        if (! $thread) {
            throw new \Exception('Action has no associated thread');
        }

        // Get recipient email from the thread's last inbound message
        $recipientEmail = $this->getRecipientEmail($thread);

        try {
            // Send the clarification email (Mailer will use ActionResponse flow to preserve Re: subject and language)
            Mail::to($recipientEmail)->send(new ActionClarificationMail($this->action, $thread));

            // Mark as sent for idempotence
            $this->action->update([
                'meta_json' => array_merge($this->action->meta_json ?? [], [
                    'clarification_sent_at' => now(),
                ]),
            ]);

            Log::info('Clarification email sent successfully', [
                'action_id' => $this->action->id,
                'to' => $recipientEmail,
                'thread_id' => $thread->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send clarification email', [
                'action_id' => $this->action->id,
                'to' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the recipient email from the thread's last inbound message.
     */
    private function getRecipientEmail(Thread $thread): string
    {
        $lastInboundMessage = $thread->emailMessages()
            ->where('direction', 'inbound')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastInboundMessage) {
            throw new \Exception('No inbound message found in thread');
        }

        // First, prefer normalized column if available
        $from = trim((string) ($lastInboundMessage->from_email ?? ''));
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }

        $headers = $lastInboundMessage->headers_json ?? [];

        // Headers are stored as array of [Name => ..., Value => ...] pairs
        $headerMap = [];
        foreach ($headers as $header) {
            if (isset($header['Name']) && isset($header['Value'])) {
                $headerMap[strtolower($header['Name'])] = $header['Value'];
            }
        }

        // Try different header fields for sender email
        $possibleFields = ['from', 'sender', 'reply-to', 'return-path'];

        foreach ($possibleFields as $field) {
            if (isset($headerMap[$field])) {
                $value = $headerMap[$field];

                // Extract email from "Name <email>" format
                if (preg_match('/<([^>]+)>/', $value, $matches)) {
                    return $matches[1];
                }
                // Extract email from various formats
                if (preg_match('/\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/', $value, $matches)) {
                    return $matches[1];
                }
            }
        }

        throw new \Exception('Could not extract sender email from message headers: '.json_encode($headerMap));
    }
}
