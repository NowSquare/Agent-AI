<?php

namespace App\Jobs;

use App\Mcp\Tools\ActionInterpretationTool;
use App\Models\Account;
use App\Models\Action;
use App\Models\EmailInboundPayload;
use App\Models\EmailMessage;
use App\Models\Memory;
use App\Services\LlmClient;
use App\Services\ReplyCleaner;
use App\Services\ThreadResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30; // 30 seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $payloadId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ReplyCleaner $replyCleaner,
        ThreadResolver $threadResolver,
        LlmClient $llmClient,
        ActionInterpretationTool $actionInterpreter
    ): void {
        Log::info('Processing inbound email', ['payload_id' => $this->payloadId]);

        // Retrieve and decrypt payload
        try {
            $payloadRecord = EmailInboundPayload::findOrFail($this->payloadId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Payload not found for processing', [
                'payload_id' => $this->payloadId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Handle binary ciphertext field (PostgreSQL may return as resource or encoded)
        $ciphertext = $payloadRecord->ciphertext;
        if (is_resource($ciphertext)) {
            $ciphertext = stream_get_contents($ciphertext);
        }

        // Ensure we have a string
        if (! is_string($ciphertext)) {
            $ciphertext = (string) $ciphertext;
        }

        try {
            $payload = json_decode(Crypt::decryptString($ciphertext), true);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error('Failed to decrypt payload', [
                'payload_id' => $this->payloadId,
                'ciphertext_length' => strlen($ciphertext),
                'ciphertext_preview' => substr($ciphertext, 0, 50),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (! $payload) {
            Log::error('Failed to decrypt or decode payload', ['payload_id' => $this->payloadId]);

            return;
        }

        // Parse Postmark payload
        $emailData = $this->parsePostmarkPayload($payload);

        // Validate required fields
        if (empty($emailData['message_id'])) {
            Log::warning('Skipping payload with missing MessageID', [
                'payload_id' => $this->payloadId,
                'subject' => $emailData['subject'] ?? 'N/A',
            ]);

            return;
        }

        // Check if message already exists (prevent duplicates)
        if (EmailMessage::where('message_id', $emailData['message_id'])->exists()) {
            Log::info('Email message already exists, skipping duplicate', [
                'message_id' => $emailData['message_id'],
                'payload_id' => $this->payloadId,
            ]);

            return;
        }

        // Get or create account (for now, use default account)
        $account = Account::firstOrCreate([
            'name' => 'Default Account',
        ]);

        // Clean reply text
        $cleanReply = $replyCleaner->clean($emailData['text_body'] ?? '', $emailData['html_body'] ?? '');

        // Resolve thread
        $thread = $threadResolver->resolveOrCreate(
            account: $account,
            subject: $emailData['subject'],
            messageId: $emailData['message_id'],
            inReplyTo: $emailData['in_reply_to'],
            references: $emailData['references']
        );

        // Create email message
        $messageData = [
            'thread_id' => $thread->id,
            'direction' => 'inbound',
            'processing_status' => 'queued', // Job dispatched, ready for LLM processing
            'message_id' => $emailData['message_id'],
            'in_reply_to' => $emailData['in_reply_to'],
            'references' => $emailData['references'],
            'from_email' => $emailData['from_email'],
            'from_name' => $emailData['from_name'],
            'to_json' => $emailData['to'],
            'cc_json' => $emailData['cc'] ?? [],
            'bcc_json' => $emailData['bcc'] ?? [],
            'subject' => $emailData['subject'],
            'headers_json' => $emailData['headers'] ?? [],
            'body_text' => $emailData['text_body'],
            'body_html' => $emailData['html_body'],
            'x_thread_id' => $emailData['x_thread_id'] ?? null,
            'raw_size_bytes' => $emailData['raw_size_bytes'] ?? null,
        ];

        $emailMessage = EmailMessage::create($messageData);

        // Register attachments (if any)
        if (! empty($emailData['attachments'])) {
            $this->registerAttachments($emailMessage, $emailData['attachments']);
        }

        Log::info('Email processed successfully', [
            'payload_id' => $this->payloadId,
            'thread_id' => $thread->id,
            'message_id' => $emailMessage->id,
            'clean_reply_length' => strlen($cleanReply),
        ]);

        // Phase 2: LLM interpretation and action processing
        $emailMessage->update(['processing_status' => 'processing']);
        $this->processWithLLM($llmClient, $emailMessage, $thread, $cleanReply, $account);
    }

    /**
     * Parse Postmark inbound webhook payload.
     */
    private function parsePostmarkPayload(array $payload): array
    {
        return [
            'message_id' => $payload['MessageID'] ?? null,
            'in_reply_to' => $payload['InReplyTo'] ?? null,
            'references' => $payload['References'] ?? null,
            'from_email' => $this->extractEmail($payload['From'] ?? ''),
            'from_name' => $this->extractName($payload['From'] ?? ''),
            'to' => $this->parseRecipients($payload['To'] ?? ''),
            'cc' => $this->parseRecipients($payload['Cc'] ?? ''),
            'bcc' => $this->parseRecipients($payload['Bcc'] ?? ''),
            'subject' => $payload['Subject'] ?? '',
            'text_body' => $payload['TextBody'] ?? '',
            'html_body' => $payload['HtmlBody'] ?? '',
            'headers' => $payload['Headers'] ?? [],
            'x_thread_id' => $this->findHeader($payload['Headers'] ?? [], 'X-Thread-ID'),
            'attachments' => $payload['Attachments'] ?? [],
            'raw_size_bytes' => strlen($payload['TextBody'] ?? '') + strlen($payload['HtmlBody'] ?? ''),
        ];
    }

    /**
     * Extract email from "Name <email>" format.
     */
    private function extractEmail(string $address): string
    {
        if (preg_match('/<([^>]+)>/', $address, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return strtolower(trim($address));
    }

    /**
     * Extract name from "Name <email>" format.
     */
    private function extractName(string $address): string
    {
        if (preg_match('/^([^<]+)</', $address, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Parse recipients string into array format.
     */
    private function parseRecipients(string $recipients): array
    {
        if (empty($recipients)) {
            return [];
        }

        $result = [];
        $parts = explode(',', $recipients);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $result[] = [
                'email' => $this->extractEmail($part),
                'name' => $this->extractName($part),
            ];
        }

        return $result;
    }

    /**
     * Find header value by name.
     */
    private function findHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (strtolower($header['Name'] ?? '') === strtolower($name)) {
                return $header['Value'] ?? null;
            }
        }

        return null;
    }

    /**
     * Register attachments for the email message.
     */
    private function registerAttachments(EmailMessage $emailMessage, array $attachments): void
    {
        // TODO: Implement attachment processing
        // For now, just log them
        Log::info('Attachments found', [
            'message_id' => $emailMessage->id,
            'count' => count($attachments),
        ]);
    }

    /**
     * Process email with LLM interpretation and action dispatch.
     */
    private function processWithLLM(
        LlmClient $llmClient,
        EmailMessage $emailMessage,
        $thread,
        string $cleanReply,
        Account $account
    ): void {
        try {
            // Detect language (fallback if library fails)
            $locale = $this->detectLanguage($llmClient, $cleanReply);

            // Get thread summary for context
            $threadSummary = $this->getThreadSummary($thread);

            // Get recent memories for context
            $recentMemories = $this->getRecentMemories($account, $thread);

            // Get attachments excerpt (placeholder for now)
            $attachmentsExcerpt = ''; // TODO: Implement attachment text extraction

            // Interpret action using MCP tool
            $interpretation = $this->interpretActionWithMCP(
                $actionInterpreter,
                $cleanReply,
                $threadSummary,
                $attachmentsExcerpt,
                $recentMemories
            );

            // Create action based on MCP interpretation
            $action = $this->createActionFromMCPInterpretation(
                $interpretation,
                $emailMessage,
                $thread,
                $account
            );

            // Extract and store memories
            $this->extractMemories($llmClient, $emailMessage, $thread, $account, $cleanReply, $locale);

            // Mark as successfully processed
            $emailMessage->update([
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            Log::info('LLM processing completed', [
                'message_id' => $emailMessage->id,
                'action_type' => $interpretation['action_type'] ?? null,
                'confidence' => $interpretation['confidence'] ?? null,
                'needs_clarification' => $interpretation['needs_clarification'] ?? false,
            ]);

        } catch (\Throwable $e) {
            Log::error('LLM processing failed', [
                'message_id' => $emailMessage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed
            $emailMessage->update([
                'processing_status' => 'failed',
                'processed_at' => now(),
            ]);

            // TODO: Send fallback "options" email when LLM fails
            // For now, just create a low-confidence action that will trigger options email
            $this->createFallbackAction($emailMessage, $thread, $account);
        }
    }

    /**
     * Detect language from clean reply text.
     */
    private function detectLanguage(LlmClient $llmClient, string $text): string
    {
        // TODO: Use language detection library first
        // For now, fallback to LLM detection

        try {
            $result = $llmClient->call('language_detect', [
                'sample_text' => substr($text, 0, 200), // Limit text length
            ]);

            // Extract language code from response (simple parsing)
            $result = trim(strtolower($result));
            if (in_array($result, ['en', 'nl', 'fr', 'de', 'it', 'es'])) {
                return $result.'_US'; // Simple locale mapping
            }

            return 'en_US';
        } catch (\Throwable $e) {
            Log::warning('Language detection failed, using default', ['error' => $e->getMessage()]);

            return 'en_US';
        }
    }

    /**
     * Get thread summary for LLM context.
     */
    private function getThreadSummary($thread): string
    {
        // TODO: Implement proper thread summarization
        // For now, return basic thread info
        return "Thread: {$thread->subject}";
    }

    /**
     * Get recent memories for context.
     */
    private function getRecentMemories(Account $account, $thread): array
    {
        // TODO: Implement memory retrieval with decay
        // For now, return empty array
        return [];
    }

    /**
     * Create action from LLM interpretation.
     */
    private function createActionFromInterpretation(
        array $interpretation,
        EmailMessage $emailMessage,
        $thread,
        Account $account,
        string $locale
    ): Action {
        $action = Action::create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'email_message_id' => $emailMessage->id,
            'type' => $interpretation['action_type'],
            'payload_json' => $interpretation['parameters'] ?? [],
            'scope_hint' => $interpretation['scope_hint'] ?? null,
            'confidence' => $interpretation['confidence'] ?? 0.0,
            'clarification_rounds' => 0,
            'clarification_max' => 2,
            'status' => 'pending',
            'locale' => $locale,
        ]);

        // Handle clarification if needed
        if (($interpretation['needs_clarification'] ?? false) && $interpretation['clarification_prompt']) {
            // TODO: Send clarification email
            $action->update([
                'clarification_rounds' => 1,
                'last_clarification_sent_at' => now(),
            ]);
        }

        return $action;
    }

    /**
     * Extract and store memories from the email.
     */
    private function extractMemories(
        LlmClient $llmClient,
        EmailMessage $emailMessage,
        $thread,
        Account $account,
        string $cleanReply,
        string $locale
    ): void {
        try {
            $memoryResult = $llmClient->json('memory_extract', [
                'detected_locale' => $locale,
                'clean_reply' => $cleanReply,
                'thread_summary' => $this->getThreadSummary($thread),
                'attachments_excerpt' => '', // TODO: Implement
            ]);

            foreach ($memoryResult['items'] ?? [] as $item) {
                Memory::create([
                    'account_id' => $account->id,
                    'scope' => $item['scope'],
                    'scope_id' => match ($item['scope']) {
                        'conversation' => $thread->id,
                        'user' => null, // TODO: Get user ID
                        'account' => $account->id,
                    },
                    'key' => $item['key'],
                    'value_json' => $item['value'] ?? null,
                    'confidence' => $item['confidence'] ?? 0.5,
                    'ttl_category' => $item['ttl_category'] ?? 'volatile',
                    'expires_at' => $this->calculateExpiry($item['ttl_category']),
                    'provenance' => $item['provenance'] ?? "email_message_id:{$emailMessage->id}",
                ]);
            }

        } catch (\Throwable $e) {
            Log::warning('Memory extraction failed', [
                'message_id' => $emailMessage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate memory expiry based on TTL category.
     */
    private function calculateExpiry(string $category): ?\Carbon\Carbon
    {
        return match ($category) {
            'volatile' => now()->addDays(30),
            'seasonal' => now()->addDays(120),
            'durable' => now()->addDays(730),
            'legal' => null, // No expiry
            default => now()->addDays(30),
        };
    }

    /**
     * Interpret action using MCP ActionInterpretationTool.
     */
    private function interpretActionWithMCP(ActionInterpretationTool $actionInterpreter, string $cleanReply, string $threadSummary, string $attachmentsExcerpt, array $recentMemories): array
    {
        try {
            // Create a mock Request object for the MCP tool
            $request = new class($cleanReply, $threadSummary, $attachmentsExcerpt, $recentMemories) extends \Laravel\Mcp\Request
            {
                public function __construct(
                    private string $cleanReply,
                    private string $threadSummary,
                    private string $attachmentsExcerpt,
                    private array $recentMemories
                ) {}

                public function string(string $key, ?string $default = null): string
                {
                    return match ($key) {
                        'clean_reply' => $this->cleanReply,
                        'thread_summary' => $this->threadSummary,
                        'attachments_excerpt' => $this->attachmentsExcerpt,
                        default => $default ?? ''
                    };
                }

                public function array(string $key, array $default = []): array
                {
                    return match ($key) {
                        'recent_memories' => $this->recentMemories,
                        default => $default
                    };
                }
            };

            $response = $actionInterpreter->handle($request);

            return $response->getData(); // Get the JSON data from the response

        } catch (\Exception $e) {
            Log::error('MCP Action interpretation failed', [
                'error' => $e->getMessage(),
            ]);

            // Return safe fallback
            return [
                'action_type' => 'info_request',
                'parameters' => ['question' => $cleanReply],
                'confidence' => 0.5,
                'needs_clarification' => false,
                'clarification_prompt' => null,
            ];
        }
    }

    /**
     * Create action from MCP interpretation.
     */
    private function createActionFromMCPInterpretation(array $interpretation, EmailMessage $emailMessage, $thread, Account $account): Action
    {
        $actionType = $interpretation['action_type'] ?? 'info_request';
        $parameters = $interpretation['parameters'] ?? ['question' => ''];

        return Action::create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'type' => $actionType,
            'payload_json' => $parameters,
            'status' => 'pending',
        ]);
    }

    /**
     * Create fallback action when LLM processing fails.
     */
    private function createFallbackAction(EmailMessage $emailMessage, $thread, Account $account): void
    {
        Action::create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'type' => 'options_fallback',
            'payload_json' => [
                'email_message_id' => $emailMessage->id, // Store in payload instead
                'confidence' => 0.0,
                'locale' => 'en_US',
            ],
            'clarification_rounds' => 0,
            'clarification_max' => 2,
            'status' => 'pending',
        ]);

        Log::info('Created fallback action due to LLM failure', [
            'message_id' => $emailMessage->id,
        ]);
    }
}
