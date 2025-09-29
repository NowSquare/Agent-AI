<?php

namespace App\Jobs;

use App\Mail\ActionResponseMail;
use App\Models\Action;
use App\Models\Thread;
use App\Services\LanguageDetector;
use App\Services\LlmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOptionsEmail implements ShouldQueue
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
        Log::info('Sending options email', [
            'action_id' => $this->action->id,
            'action_type' => $this->action->type,
        ]);

        // Check idempotence: don't send if already sent
        if ($this->action->meta_json['options_sent_at'] ?? false) {
            Log::info('Options email already sent, skipping', [
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
            // Draft a localized options email via LLM and send as threaded reply (Re: <subject>)
            $lastInbound = $thread->emailMessages()->where('direction', 'inbound')->latest('created_at')->first();
            $detector = app(LanguageDetector::class);
            $locale = $detector->detect($lastInbound?->body_text ?? $lastInbound?->subject ?? '');

            $llm = app(LlmClient::class);
            $draft = $llm->json('options_email_draft', [
                'detected_locale' => $locale,
                'original_subject' => (string) ($lastInbound?->subject ?? ''),
                'context_hint' => (string) ($this->action->payload_json['question'] ?? ''),
            ]);

            $bodyText = (string) ($draft['text'] ?? '');
            if (trim($bodyText) === '') {
                Log::warning('Options draft empty; skipping send to avoid boilerplate', [
                    'action_id' => $this->action->id,
                ]);
                return;
            }

            Mail::to($recipientEmail)->send(new ActionResponseMail($this->action, $thread, $bodyText));

            // Mark as sent for idempotence
            $this->action->update([
                'meta_json' => array_merge($this->action->meta_json ?? [], [
                'options_sent_at' => now(),
                ]),
            ]);

            Log::info('Options email sent successfully', [
                'action_id' => $this->action->id,
                'to' => $recipientEmail,
                'thread_id' => $thread->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send options email', [
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

        $headers = $lastInboundMessage->headers_json ?? [];

        // Headers are stored as array of [Name => ..., Value => ...] pairs
        $headerMap = [];
        foreach ($headers as $header) {
            if (isset($header['Name']) && isset($header['Value'])) {
                $headerMap[strtolower($header['Name'])] = $header['Value'];
            }
        }

        // Try different header fields for sender email; fallback to from_email column
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

        // Fallback: use normalized from_email on the email message if headers are missing
        if (! empty($lastInboundMessage->from_email)) {
            return $lastInboundMessage->from_email;
        }

        throw new \Exception('Could not extract sender email from message headers: '.json_encode($headerMap));
    }
}
