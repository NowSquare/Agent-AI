<?php

namespace App\Jobs;

use App\Mcp\Tools\ActionInterpretationTool;
use App\Models\Account;
use App\Models\Action;
use App\Models\Attachment;
use App\Models\EmailInboundPayload;
use App\Models\EmailMessage;
use App\Models\Memory;
use App\Services\AttachmentService;
use App\Services\LanguageDetector;
use App\Services\LlmClient;
use App\Services\MemoryService;
use App\Services\ReplyCleaner;
use App\Services\ThreadResolver;
use App\Services\ThreadSummarizer;
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
        ActionInterpretationTool $actionInterpreter,
        AttachmentService $attachmentService,
        MemoryService $memoryService
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

        // Ensure default account (single-tenant bootstrap)
        $account = \App\Services\EnsureDefaultAccount::run();

        // Ensure Contact for sender (normalize email)
        $senderEmail = strtolower(trim($emailData['from_email'] ?? ''));
        if ($senderEmail !== '') {
            \App\Models\Contact::firstOrCreate([
                'account_id' => $account->id,
                'email' => $senderEmail,
            ], [
                'name' => $emailData['from_name'] ?? null,
            ]);
        }

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

        // Create and store embedding for the email body (if present)
        if (! empty($emailMessage->body_text)) {
            $embedSvc = app(\App\Services\Embeddings::class);
            $vec = $embedSvc->embedText($emailMessage->body_text);
            $embedSvc->storeEmailBodyEmbedding($emailMessage->id, $vec);
        }

        // Register attachments (if any)
        if (! empty($emailData['attachments'])) {
            $this->registerAttachments($emailMessage, $emailData['attachments'], $attachmentService);
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
    private function registerAttachments(EmailMessage $emailMessage, array $attachments, AttachmentService $attachmentService): void
    {
        $maxTotalSize = config('attachments.total_max_size_mb', 40) * 1024 * 1024;
        $totalSize = 0;
        $storedCount = 0;

        Log::info('Processing attachments', [
            'message_id' => $emailMessage->id,
            'count' => count($attachments),
        ]);

        foreach ($attachments as $attachmentData) {
            // Convert Postmark attachment format to UploadedFile-like object
            $fileContent = base64_decode($attachmentData['Content'] ?? '');
            if (empty($fileContent)) {
                Log::warning('Empty attachment content, skipping', [
                    'message_id' => $emailMessage->id,
                    'attachment_name' => $attachmentData['Name'] ?? 'unknown',
                ]);

                continue;
            }

            $fileSize = strlen($fileContent);

            // Check total size limit
            if ($totalSize + $fileSize > $maxTotalSize) {
                Log::warning('Attachment total size limit exceeded, skipping remaining', [
                    'message_id' => $emailMessage->id,
                    'current_total' => $totalSize,
                    'new_size' => $fileSize,
                    'limit' => $maxTotalSize,
                ]);
                break;
            }

            // Create a temporary UploadedFile-like object
            $tempFile = tmpfile();
            fwrite($tempFile, $fileContent);
            fseek($tempFile, 0);

            $uploadedFile = new class($tempFile, $attachmentData['Name'] ?? 'unknown', $attachmentData['ContentType'] ?? 'application/octet-stream', $fileSize) extends \Illuminate\Http\UploadedFile
            {
                private int $fileSize;

                public function __construct($tempFile, $name, $mime, $size)
                {
                    parent::__construct(stream_get_meta_data($tempFile)['uri'], $name, $mime, null, true);
                    $this->fileSize = $size;
                }

                public function getSize(): int
                {
                    return $this->fileSize;
                }
            };

            // Store attachment using service
            $attachment = $attachmentService->store($uploadedFile, $emailMessage->id);

            if ($attachment) {
                $totalSize += $fileSize;
                $storedCount++;

                // Dispatch scan job
                ScanAttachment::dispatch($attachment->id)->onQueue('attachments');

                Log::info('Attachment stored and scan dispatched', [
                    'message_id' => $emailMessage->id,
                    'attachment_id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size_bytes' => $attachment->size_bytes,
                ]);
            } else {
                Log::warning('Attachment storage failed', [
                    'message_id' => $emailMessage->id,
                    'filename' => $attachmentData['Name'] ?? 'unknown',
                    'mime' => $attachmentData['ContentType'] ?? 'unknown',
                    'size_bytes' => $fileSize,
                ]);
            }

            // Clean up temp file
            fclose($tempFile);
        }

        Log::info('Attachment processing completed', [
            'message_id' => $emailMessage->id,
            'total_attachments' => count($attachments),
            'stored_count' => $storedCount,
            'total_size_bytes' => $totalSize,
        ]);
    }

    /**
     * Get attachments excerpt for LLM context.
     */
    private function getAttachmentsExcerpt(EmailMessage $emailMessage): string
    {
        $attachments = $emailMessage->attachments()->get();

        if ($attachments->isEmpty()) {
            return '';
        }

        $excerpts = [];
        foreach ($attachments as $attachment) {
            $excerpt = $attachment->getAttachmentsExcerpt();
            if (! empty($excerpt)) {
                $excerpts[] = "Attachment: {$attachment->filename}\n{$excerpt}";
            }
        }

        if (empty($excerpts)) {
            return '';
        }

        // Combine excerpts, keeping under LLM token limits
        $combined = implode("\n\n", $excerpts);

        Log::info('Attachments excerpt assembled for LLM', [
            'message_id' => $emailMessage->id,
            'attachments_count' => $attachments->count(),
            'excerpt_length' => strlen($combined),
        ]);

        return $combined;
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

            // Get attachments excerpt for LLM context
            $attachmentsExcerpt = $this->getAttachmentsExcerpt($emailMessage);

            // Interpret action using MCP tool
            $interpretation = $this->interpretActionWithMCP(
                app(ActionInterpretationTool::class),
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

            // Handle confidence-based clarification logic
            $confidence = $interpretation['confidence'] ?? 0.0;
            $this->handleConfidenceThresholds($action, $confidence);

            // Extract and store memories
            $this->extractMemories($llmClient, $emailMessage, $thread, $account, $cleanReply, $locale);

            // Mark as successfully processed (even when awaiting user input)
            $emailMessage->update([
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            Log::info('LLM processing completed', [
                'message_id' => $emailMessage->id,
                'action_type' => $interpretation['action_type'] ?? null,
                'confidence' => $confidence,
                'action_status' => $action->status,
                'clarification_needed' => $this->clarificationNeeded($confidence),
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
        $detector = app(LanguageDetector::class);

        return $detector->detect($text);
    }

    /**
     * Get thread summary for LLM context.
     */
    private function getThreadSummary($thread): string
    {
        $summarizer = app(ThreadSummarizer::class);
        $summary = $summarizer->getSummary($thread);

        return $summary['summary'] ?? "Thread: {$thread->subject}";
    }

    /**
     * Get recent memories for context.
     */
    private function getRecentMemories(Account $account, $thread): string
    {
        $memoryService = app(MemoryService::class);

        // Get memories from all scopes, ordered by relevance
        $memories = collect([]);

        // Thread-specific memories
        $threadMemories = $memoryService->retrieve(
            Memory::SCOPE_CONVERSATION,
            $thread->id,
            null,
            3
        );
        $memories = $memories->merge($threadMemories);

        // Account-level memories
        $accountMemories = $memoryService->retrieve(
            Memory::SCOPE_ACCOUNT,
            $account->id,
            null,
            3
        );
        $memories = $memories->merge($accountMemories);

        // Format memories for prompt context
        if ($memories->isEmpty()) {
            return '';
        }

        $excerpt = "Relevant Context:\n";
        foreach ($memories as $memory) {
            $value = is_array($memory->value_json) ? json_encode($memory->value_json) : $memory->value_json;
            $excerpt .= "- {$memory->key}: {$value}\n";
        }

        // Truncate if too long (keep under typical token limits)
        if (strlen($excerpt) > config('memory.max_excerpt_chars', 1200)) {
            $excerpt = substr($excerpt, 0, config('memory.max_excerpt_chars', 1200))."...\n";
        }

        return $excerpt;
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
            // Use MCP tool with schema validation for memory extraction
            $memoryResult = app(\App\Mcp\Tools\MemoryExtractTool::class)
                ->runReturningArray(
                    $locale,
                    $cleanReply,
                    $this->getThreadSummary($thread),
                    $this->getAttachmentsExcerpt($emailMessage)
                );

            $memoryService = app(MemoryService::class);
            $meta = [
                'prompt_key' => 'memory_extract',
                'model' => config('llm.default_model'),
                'locale' => $locale,
            ];

            foreach ($memoryResult['items'] ?? [] as $item) {
                $scopeId = match ($item['scope']) {
                    Memory::SCOPE_CONVERSATION => $thread->id,
                    Memory::SCOPE_USER => null, // TODO: Get user ID
                    Memory::SCOPE_ACCOUNT => $account->id,
                    default => null,
                };

                if (! $scopeId) {
                    continue;
                }

                // Ensure value is an array payload
                $memoryValue = $item['value'] ?? [];
                if (! is_array($memoryValue)) {
                    $memoryValue = ['value' => (string) $memoryValue];
                }

                $memoryService->writeGate(
                    scope: $item['scope'],
                    scopeId: $scopeId,
                    key: $item['key'],
                    value: $memoryValue,
                    confidence: $item['confidence'] ?? 0.5,
                    ttlClass: $item['ttl_category'] ?? Memory::TTL_VOLATILE,
                    emailMessageId: $emailMessage->message_id,
                    threadId: $thread->id,
                    meta: $meta
                );
            }

            Log::info('Memory extraction completed', [
                'message_id' => $emailMessage->id,
                'memories_count' => count($memoryResult['items'] ?? []),
            ]);

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
    private function interpretActionWithMCP(ActionInterpretationTool $actionInterpreter, string $cleanReply, string $threadSummary, string $attachmentsExcerpt, string $recentMemories): array
    {
        try {
            // Create a mock Request object for the MCP tool
            return $actionInterpreter->runReturningArray(
                cleanReply: $cleanReply,
                threadSummary: $threadSummary,
                attachmentsExcerpt: $attachmentsExcerpt,
                recentMemories: is_array($recentMemories) ? $recentMemories : []
            );

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

    /**
     * Handle confidence-based clarification logic.
     */
    private function handleConfidenceThresholds(Action $action, float $confidence): void
    {
        if ($confidence >= 0.75) {
            // High confidence: auto-dispatch immediately
            Log::info('High confidence action, dispatching immediately', [
                'action_id' => $action->id,
                'confidence' => $confidence,
            ]);
            $this->dispatchAction($action);
        } elseif ($confidence >= 0.50) {
            // Medium confidence: send clarification email
            Log::info('Medium confidence action, sending clarification email', [
                'action_id' => $action->id,
                'confidence' => $confidence,
            ]);
            $action->update(['status' => 'awaiting_confirmation']);
            \App\Jobs\SendClarificationEmail::dispatch($action);
        } else {
            // Low confidence: send options email
            Log::info('Low confidence action, sending options email', [
                'action_id' => $action->id,
                'confidence' => $confidence,
            ]);
            $action->update(['status' => 'awaiting_input']);
            \App\Jobs\SendOptionsEmail::dispatch($action);
        }
    }

    /**
     * Check if clarification is needed based on confidence.
     */
    private function clarificationNeeded(float $confidence): bool
    {
        return $confidence < 0.75;
    }

    /**
     * Dispatch an action for processing.
     */
    private function dispatchAction(Action $action): void
    {
        $dispatcher = app(\App\Services\ActionDispatcher::class);
        $dispatcher->dispatch($action);
    }
}
