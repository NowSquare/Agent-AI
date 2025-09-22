<?php

namespace App\Services;

use App\Models\Thread;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThreadSummarizer
{
    public function __construct(
        private readonly LlmClient $llmClient
    ) {}

    /**
     * Get or generate thread summary with caching.
     */
    public function getSummary(Thread $thread, bool $forceRefresh = false): array
    {
        $cacheKey = "thread_summary:{$thread->id}";
        
        if (!$forceRefresh && $cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            // Get recent messages for context
            $messages = $thread->emailMessages()
                ->latest()
                ->take(5)
                ->get();

            if ($messages->isEmpty()) {
                return $this->getEmptySummary($thread);
            }

            // Build message context
            $messageContext = $messages->map(function ($message) {
                return sprintf(
                    "%s (%s): %s",
                    $message->from_name ?: $message->from_email,
                    $message->created_at->diffForHumans(),
                    $message->body_text ?: strip_tags($message->body_html)
                );
            })->join("\n\n");

            // Get key memories for context
            $memoryService = app(MemoryService::class);
            $memories = $memoryService->retrieve(
                'conversation',
                $thread->id,
                null,
                3
            );

            $memoryContext = '';
            if ($memories->isNotEmpty()) {
                $memoryContext = "Key Context:\n" . $memories->map(function ($memory) {
                    $value = is_array($memory->value_json) ? json_encode($memory->value_json) : $memory->value_json;
                    return "- {$memory->key}: {$value}";
                })->join("\n");
            }

            // Call LLM for summarization
            $result = $this->llmClient->json('thread_summarize', [
                'detected_locale' => app()->getLocale(),
                'last_messages' => $messageContext,
                'key_memories' => $memoryContext,
            ]);

            // Validate and normalize response
            $summary = [
                'summary' => $result['summary'] ?? '',
                'key_entities' => $result['key_entities'] ?? [],
                'open_questions' => $result['open_questions'] ?? [],
                'generated_at' => now()->toISOString(),
                'message_count' => $messages->count(),
            ];

            // Cache with TTL based on thread activity
            $ttl = $this->getCacheTtl($thread);
            Cache::put($cacheKey, $summary, $ttl);

            Log::info('Thread summary generated', [
                'thread_id' => $thread->id,
                'summary_length' => strlen($summary['summary']),
                'entities_count' => count($summary['key_entities']),
                'questions_count' => count($summary['open_questions']),
                'ttl_minutes' => $ttl->diffInMinutes(now()),
            ]);

            return $summary;

        } catch (\Throwable $e) {
            Log::error('Thread summarization failed', [
                'thread_id' => $thread->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getEmptySummary($thread);
        }
    }

    /**
     * Get empty summary structure.
     */
    private function getEmptySummary(Thread $thread): array
    {
        return [
            'summary' => "Thread: {$thread->subject}",
            'key_entities' => [],
            'open_questions' => [],
            'generated_at' => now()->toISOString(),
            'message_count' => 0,
        ];
    }

    /**
     * Calculate cache TTL based on thread activity.
     */
    private function getCacheTtl(Thread $thread): \Carbon\Carbon
    {
        $lastActivity = $thread->last_activity_at ?? $thread->created_at;
        $hoursSinceActivity = $lastActivity->diffInHours(now());

        // More recent = shorter TTL (more likely to change)
        if ($hoursSinceActivity < 1) {
            return now()->addMinutes(15); // Very active
        } elseif ($hoursSinceActivity < 24) {
            return now()->addHours(1); // Active today
        } elseif ($hoursSinceActivity < 168) { // 1 week
            return now()->addHours(4); // Active this week
        } else {
            return now()->addDay(); // Inactive
        }
    }
}
