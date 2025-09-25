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
use Illuminate\Support\Str;

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
        $thread = $this->action->thread;
        if (! $thread) {
            throw new \Exception('Action has no associated thread');
        }

        $responseContent = $this->getResponseContent($thread);
        if (trim($responseContent) === '') {
            // Always respond: generate a concise clarification/options prompt in the detected locale
            $lastInbound = $thread->emailMessages()->where('direction', 'inbound')->latest('created_at')->first();
            $locale = app(LanguageDetector::class)->detect($lastInbound?->body_text ?? $lastInbound?->subject ?? '');

            try {
                $llm = app(LlmClient::class);
                $clarify = $llm->json('clarify_email_draft', [
                    'detected_locale' => $locale,
                    'question' => 'Could you clarify your request so we can proceed? For example, specify the goal, constraints, and any files we should consider.',
                ]);

                $responseContent = (string) ($clarify['text'] ?? 'Could you clarify your request so we can proceed?');
            } catch (\Throwable $e) {
                $responseContent = 'Could you clarify your request so we can proceed? Please add the goal, constraints, and any needed files or links.';
            }
        }

        Log::info('Sending action response', [
            'action_id' => $this->action->id,
            'type' => $this->action->type,
        ]);

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
    private function getResponseContent(Thread $thread): string
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

        // Outcome-based fallbacks around attachments
        $lastInbound = $thread->emailMessages()->where('direction', 'inbound')->latest('created_at')->first();
        if ($lastInbound) {
            $attachmentsQuery = $lastInbound->attachments();
            if ($attachmentsQuery->exists()) {
                if ($attachmentsQuery->where('scan_status', 'infected')->exists()) {
                    // Build personalized incident explanation via LLM
                    $detector = app(LanguageDetector::class);
                    $locale = $detector->detect($lastInbound->body_text ?? $lastInbound->subject ?? '');

                    // Only include truly infected attachments in the incident list
                    $files = $lastInbound->attachments()
                        ->where('scan_status', 'infected')
                        ->get(['filename', 'scan_status', 'scan_result']);
                    $fileList = collect($files)->map(function ($f) {
                        $reason = $f->scan_status === 'infected' ? ($f->scan_result ?: 'infected') : ($f->scan_result ?: $f->scan_status);

                        return $f->filename.': '.$reason;
                    })->implode("\n");

                    try {
                        $llm = app(LlmClient::class);
                        $json = $llm->json('incident_email_draft', [
                            'detected_locale' => $locale,
                            'issue' => 'attachments_infected',
                            'original_subject' => (string) $lastInbound->subject,
                            'file_list' => $fileList,
                            'user_message' => (string) ($lastInbound->body_text ?? ''),
                        ]);

                        // Log an agent step for transparency
                        \App\Models\AgentStep::create([
                            'account_id' => $thread->account_id,
                            'thread_id' => $thread->id,
                            'action_id' => $this->action->id,
                            'role' => 'GROUNDED',
                            'provider' => 'ollama',
                            'model' => 'gpt-oss:20b',
                            'step_type' => 'incident',
                            'input_json' => [
                                'prompt_key' => 'incident_email_draft',
                                'detected_locale' => $locale,
                                'issue' => 'attachments_infected',
                                'file_list' => $fileList,
                            ],
                            'output_json' => [
                                'subject' => $json['subject'] ?? null,
                                'text' => $json['text'] ?? null,
                            ],
                            'tokens_input' => 0,
                            'tokens_output' => 0,
                            'tokens_total' => 0,
                            'latency_ms' => 0,
                            'agent_role' => 'Worker',
                            'round_no' => 0,
                        ]);

                        // Prefer text; ActionResponseMail uses responseContent
                        if (isset($json['text']) && is_string($json['text']) && trim($json['text']) !== '') {
                            return $json['text'];
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Incident email draft generation failed; falling back to static text', [
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Fallback static line if LLM fails
                    return "We couldn't process your attachments because they failed our virus scan. Please resend clean PDFs (or share a safe link) and we'll proceed right away.";
                }
                if ($attachmentsQuery->whereNull('scan_status')->exists()) {
                    return "We received your attachments and are scanning them for safety. We'll follow up with a recommendation as soon as the scan completes.";
                }
            }
        }

        // No generic placeholders; only send when we have real content
        return '';
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
                'message_id' => (string) Str::ulid(),
                'in_reply_to' => $lastInboundMessage->message_id,
                'references' => trim(($lastInboundMessage->references ? $lastInboundMessage->references.' ' : '').$lastInboundMessage->message_id),
                // Persist the generic AGENT_MAIL as the from address for audit consistency
                'from_email' => (string) config('mail.agent_mail', env('AGENT_MAIL')),
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
        // Prefer stored normalized from_email first
        if (! empty($message->from_email)) {
            return $message->from_email;
        }

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
