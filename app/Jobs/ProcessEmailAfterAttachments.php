<?php

namespace App\Jobs;

use App\Mcp\Tools\ActionInterpretationTool;
use App\Mcp\Tools\MemoryExtractTool;
use App\Models\Account;
use App\Models\Action;
use App\Models\EmailMessage;
use App\Models\Memory;
use App\Services\LanguageDetector;
use App\Services\LlmClient;
use App\Services\MemoryService;
use App\Services\ThreadSummarizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class ProcessEmailAfterAttachments implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $emailMessageId
    ) {}

    public function handle(): void
    {
        $email = EmailMessage::find($this->emailMessageId);
        if (! $email) {
            Log::error('Deferred LLM: email message not found', [
                'email_message_id' => $this->emailMessageId,
            ]);
            return;
        }

        $thread = $email->thread;
        $account = $thread?->account;
        if (! $thread || ! $account instanceof Account) {
            Log::error('Deferred LLM: missing thread or account', [
                'email_message_id' => $email->id,
                'has_thread' => (bool) $thread,
                'has_account' => (bool) $account,
            ]);
            return;
        }

        try {
            // Detect language
            $locale = app(LanguageDetector::class)->detect((string) ($email->body_text ?? ''));

            // Thread summary
            $summaryArr = app(ThreadSummarizer::class)->getSummary($thread);
            $threadSummary = $summaryArr['summary'] ?? ("Thread: ".$thread->subject);

            // Recent memories (conversation + account)
            $memoryService = app(MemoryService::class);
            $memories = collect([])
                ->merge($memoryService->retrieve(Memory::SCOPE_CONVERSATION, $thread->id, null, 3))
                ->merge($memoryService->retrieve(Memory::SCOPE_ACCOUNT, $account->id, null, 3));
            $recentMemories = '';
            if ($memories->isNotEmpty()) {
                $recentMemories = "Relevant Context:\n";
                foreach ($memories as $m) {
                    $val = is_array($m->value_json) ? json_encode($m->value_json) : $m->value_json;
                    $recentMemories .= "- {$m->key}: {$val}\n";
                }
            }

            // Build both a compact excerpt and a detailed block (summary + raw excerpt)
            $excerptBlocks = [];
            $detailedBlocks = [];
            $fullBlocks = [];
            foreach ($email->attachments as $att) {
                $hasSummary = ! empty($att->summarize_json['gist'] ?? null);
                $hasRaw = ! empty($att->extract_result_json['excerpt'] ?? '');

                $summaryLine = '';
                if ($hasSummary) {
                    $gist = (string) ($att->summarize_json['gist'] ?? '');
                    $points = $att->summarize_json['key_points'] ?? [];
                    $summaryLine = trim($gist.' '.implode(' ', array_slice((array) $points, 0, 3)));
                }
                $rawLine = $hasRaw ? (string) $att->extract_result_json['excerpt'] : '';

                if ($summaryLine !== '' || $rawLine !== '') {
                    $excerptBlocks[] = "Attachment: {$att->filename}\n".($summaryLine !== '' ? $summaryLine : $rawLine);

                    $detail = "Attachment: {$att->filename}\n";
                    if ($summaryLine !== '') {
                        $detail .= "Summary: {$summaryLine}\n";
                    }
                    if ($rawLine !== '') {
                        $detail .= "Raw Excerpt: {$rawLine}";
                    }
                    $detailedBlocks[] = trim($detail);
                }

                // Full text block (prefer full extracted text; fallback to reading file)
                $fullText = $this->getFullAttachmentText($att);
                if ($fullText !== '') {
                    // Cap very long texts to avoid token explosion
                    $maxLen = (int) config('attachments.processing.max_extracted_text_length', 50000);
                    $fullBlocks[] = "### {$att->filename} ({$att->mime})\n".substr($fullText, 0, $maxLen);
                }
            }
            $attachmentsExcerpt = implode("\n\n", $excerptBlocks);
            $attachmentsDetailed = implode("\n\n", $detailedBlocks);
            $attachmentsFull = implode("\n\n", $fullBlocks);

            // Interpret action via MCP tool
            $interp = app(ActionInterpretationTool::class)->runReturningArray(
                cleanReply: (string) ($email->body_text ?? ''),
                threadSummary: $threadSummary,
                attachmentsExcerpt: $attachmentsExcerpt,
                recentMemories: []
            );

            // Create action and handle thresholds
            $action = Action::create([
                'account_id' => $account->id,
                'thread_id' => $thread->id,
                'email_message_id' => $email->id,
                'type' => $interp['action_type'] ?? 'info_request',
                'payload_json' => $interp['parameters'] ?? [],
                'status' => 'pending',
            ]);

            $confidence = (float) ($interp['confidence'] ?? 0.0);

            // Generate a direct final response using FULL attachment content to avoid clarifications
            try {
                $prompt = "Analyze the user's email and the FULL attachment content below. Produce a concise side-by-side comparison (as a clear list or simple table in Markdown) between the proposals, covering scope, paper, turnaround, pricing (with totals), and terms (warranty, payment, validity). Then recommend one, and justify briefly. Do not ask questions. Use the full texts, not summaries.\n\nEmail:\n".
                    (string) ($email->body_text ?? '')."\n\nAttachments (full):\n".$attachmentsFull;
                $json = app(LlmClient::class)->json('agent_response', [
                    'prompt' => $prompt,
                ]);
                $final = is_string($json['response'] ?? null) ? $json['response'] : null;
                if ($final) {
                    $payload = $action->payload_json ?? [];
                    // Store under agent_response (preferred by SendActionResponse) and keep provenance
                    $payload['agent_response'] = $final;
                    $payload['attachments_used'] = 'full_text';
                    $action->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'payload_json' => $payload,
                    ]);

                    // Send immediately and stop here to avoid downstream overwrites by orchestrator
                    \App\Jobs\SendActionResponse::dispatch($action);
                    $email->update(['processing_status' => 'processed', 'processed_at' => now()]);
                    Log::info('Deferred LLM processing completed with full-text response', [
                        'email_message_id' => $email->id,
                        'action_id' => $action->id,
                    ]);

                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('Deferred LLM: final response generation failed; proceeding with standard flow', [
                    'email_message_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);
            }
            if ($confidence >= 0.75) {
                app(\App\Services\ActionDispatcher::class)->dispatch($action);
            } elseif ($confidence >= 0.50) {
                $action->update(['status' => 'awaiting_confirmation']);
                \App\Jobs\SendClarificationEmail::dispatch($action);
            } else {
                $action->update(['status' => 'awaiting_input']);
                \App\Jobs\SendOptionsEmail::dispatch($action);
            }

            // Extract memories via MCP tool
            try {
                $memoryResult = app(MemoryExtractTool::class)->runReturningArray(
                    $locale,
                    (string) ($email->body_text ?? ''),
                    $threadSummary,
                    $attachmentsExcerpt
                );
                foreach ($memoryResult['items'] ?? [] as $item) {
                    $scopeId = match ($item['scope'] ?? null) {
                        Memory::SCOPE_CONVERSATION => $thread->id,
                        Memory::SCOPE_ACCOUNT => $account->id,
                        default => null,
                    };
                    if (! $scopeId) {
                        continue;
                    }
                    $value = $item['value'] ?? [];
                    if (! is_array($value)) {
                        $value = ['value' => (string) $value];
                    }
                    $memoryService->writeGate(
                        scope: $item['scope'],
                        scopeId: $scopeId,
                        key: (string) ($item['key'] ?? ''),
                        value: $value,
                        confidence: (float) ($item['confidence'] ?? 0.5),
                        ttlClass: (string) ($item['ttl_category'] ?? Memory::TTL_VOLATILE),
                        emailMessageId: $email->message_id,
                        threadId: $thread->id,
                        meta: ['prompt_key' => 'memory_extract', 'model' => config('llm.default_model'), 'locale' => $locale]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Deferred LLM: memory extraction failed', [
                    'email_message_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $email->update([
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            Log::info('Deferred LLM processing completed', [
                'email_message_id' => $email->id,
                'action_type' => $interp['action_type'] ?? null,
                'confidence' => $confidence,
            ]);

        } catch (\Throwable $e) {
            Log::error('Deferred LLM processing failed', [
                'email_message_id' => $email->id,
                'error' => $e->getMessage(),
            ]);
            $email->update(['processing_status' => 'failed', 'processed_at' => now()]);
        }
    }

    /**
     * Get full textual content for an attachment.
     * Prefer extracted text for text-based files; for PDFs re-run extractor for full text; otherwise read raw.
     */
    private function getFullAttachmentText($attachment): string
    {
        try {
            // For plain text/markdown/csv read raw file
            if (in_array($attachment->mime, ['text/plain', 'text/markdown', 'text/csv'])) {
                return (string) Storage::disk($attachment->storage_disk)->get($attachment->storage_path);
            }
            // For PDF attempt full extraction again (not just excerpt)
            if ($attachment->mime === 'application/pdf') {
                $path = Storage::disk($attachment->storage_disk)->path($attachment->storage_path);
                return (string) Pdf::getText($path);
            }
            // Fallback to whatever excerpt we have
            return (string) ($attachment->extract_result_json['excerpt'] ?? '');
        } catch (\Throwable $e) {
            return (string) ($attachment->extract_result_json['excerpt'] ?? '');
        }
    }
}
