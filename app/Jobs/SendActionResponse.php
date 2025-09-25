<?php

namespace App\Jobs;

use App\Mail\ActionResponseMail;
use App\Models\Action;
use App\Models\Thread;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendActionResponse implements ShouldQueue
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
        Log::info('Sending action response', [
            'action_id' => $this->action->id,
            'type' => $this->action->type,
        ]);

        $thread = $this->action->thread;
        if (! $thread) {
            throw new \Exception('Action has no associated thread');
        }

        $responseContent = $this->getResponseContent();

        // Send the response email
        $this->sendResponseEmail($thread, $responseContent);

        Log::info('Action response sent', [
            'action_id' => $this->action->id,
            'thread_id' => $thread->id,
        ]);
    }

    /**
     * Get the response content from the agent processing.
     */
    private function getResponseContent(): string
    {
        // The agent response is now stored in the action payload
        $agentResponse = $this->action->payload_json['agent_response'] ?? '';
        $final = $this->action->payload_json['final_response'] ?? '';

        if (! empty($agentResponse)) {
            return $agentResponse;
        }
        if (! empty($final)) {
            return $final;
        }

        // Fallback for actions without agent processing
        return match ($this->action->type) {
            'info_request' => "Thank you for your question. I'll provide a detailed response shortly.",
            'approve' => 'Your request has been approved.',
            'reject' => 'Your request has been reviewed and declined.',
            default => 'Your request has been processed.',
        };
    }

    /**
     * Get a summary of the thread for context.
     */
    private function getThreadSummary(): string
    {
        $messages = $this->action->thread->emailMessages()
            ->orderBy('created_at')
            ->take(5)
            ->get();

        $summary = "Thread summary:\n";
        foreach ($messages as $message) {
            $direction = $message->direction === 'inbound' ? 'User' : 'System';
            $summary .= "- {$direction}: {$message->subject}\n";
        }

        return $summary;
    }

    /**
     * Send the response email.
     */
    private function sendResponseEmail(Thread $thread, string $content): void
    {
        // Get the original sender's email from the thread
        $lastInboundMessage = $thread->emailMessages()
            ->where('direction', 'inbound')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastInboundMessage) {
            throw new \Exception('No inbound message found in thread');
        }

        $recipientEmail = $this->extractSenderEmail($lastInboundMessage);

        // Send the response email via Postmark
        try {
            Mail::to($recipientEmail)->send(new ActionResponseMail($this->action, $thread, $content));

            // Persist an outbound EmailMessage for audit and continuity
            \App\Models\EmailMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'processing_status' => 'sent',
                'message_id' => (string) \Str::ulid(),
                'in_reply_to' => $lastInboundMessage->message_id,
                'references' => trim(($lastInboundMessage->references ? $lastInboundMessage->references.' ' : '').$lastInboundMessage->message_id),
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_json' => [['email' => $recipientEmail, 'name' => '']],
                'subject' => 'Re: '.$thread->subject,
                'headers_json' => [['Name' => 'In-Reply-To', 'Value' => $lastInboundMessage->message_id]],
                'provider_message_id' => null,
                'delivery_status' => 'sent',
                'delivery_error_json' => null,
                'body_text' => $content,
                'body_html' => null,
                'x_thread_id' => $thread->id,
                'raw_size_bytes' => strlen($content),
                'processed_at' => now(),
            ]);

            Log::info('Action response email sent successfully', [
                'action_id' => $this->action->id,
                'to' => $recipientEmail,
                'subject' => "Re: {$thread->subject}",
                'thread_id' => $thread->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send action response email', [
                'action_id' => $this->action->id,
                'to' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract sender email from message headers.
     */
    private function extractSenderEmail($message): string
    {
        $headers = $message->headers_json ?? [];

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
