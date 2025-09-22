<?php

namespace App\Services;

use App\Models\Memory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class MemoryService
{
    /**
     * Write a new memory through the gate, enforcing policy rules.
     */
    public function writeGate(
        string $scope,
        string $scopeId,
        string $key,
        array $value,
        float $confidence,
        string $ttlClass,
        ?string $emailMessageId = null,
        ?string $threadId = null,
        array $meta = []
    ): ?Memory {
        // Validate minimum confidence threshold
        if ($confidence < config('memory.min_confidence_to_persist', 0.60)) {
            return null;
        }

        // Validate TTL class
        if (!in_array($ttlClass, [
            Memory::TTL_VOLATILE,
            Memory::TTL_SEASONAL,
            Memory::TTL_DURABLE,
            Memory::TTL_LEGAL,
        ])) {
            return null;
        }

        // Validate scope
        if (!in_array($scope, [
            Memory::SCOPE_CONVERSATION,
            Memory::SCOPE_USER,
            Memory::SCOPE_ACCOUNT,
        ])) {
            return null;
        }

        // Redact PII from value
        $value = $this->redactPII($value);
        if (empty($value)) {
            return null;
        }

        // Calculate expiry based on TTL class
        $expiresAt = $this->calculateExpiry($ttlClass);

        // Check for existing memory to update
        $memory = Memory::byScope($scope, $scopeId)
            ->where('key', $key)
            ->first();

        if ($memory) {
            // Merge confidence using probabilistic OR
            $newConfidence = 1 - ((1 - $memory->confidence) * (1 - $confidence));
            
            $memory->update([
                'value_json' => $value,
                'confidence' => $newConfidence,
                'last_seen_at' => now(),
                'usage_count' => $memory->usage_count + 1,
                'meta' => array_merge($memory->meta ?? [], $meta),
                'email_message_id' => $emailMessageId ?? $memory->email_message_id,
                'thread_id' => $threadId ?? $memory->thread_id,
            ]);

            return $memory;
        }

        // Create new memory
        return Memory::create([
            'scope' => $scope,
            'scope_id' => $scopeId,
            'key' => $key,
            'value_json' => $value,
            'confidence' => $confidence,
            'ttl_category' => $ttlClass,
            'expires_at' => $expiresAt,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'meta' => $meta,
            'email_message_id' => $emailMessageId,
            'thread_id' => $threadId,
            'provenance' => $this->buildProvenance($emailMessageId, $threadId, $meta),
        ]);
    }

    /**
     * Retrieve relevant memories for a given scope, applying scoring and filtering.
     */
    public function retrieve(
        string $scope,
        string $scopeId,
        ?string $key = null,
        int $limit = 10
    ): Collection {
        $query = Memory::byScope($scope, $scopeId)
            ->active()
            ->when($key, fn($q) => $q->where('key', $key));

        return $query->get()
            ->map(fn($memory) => [
                'memory' => $memory,
                'score' => $this->calculateScore($memory)
            ])
            ->filter(fn($item) => $item['score'] >= config('memory.include_threshold', 0.45))
            ->sortByDesc('score')
            ->take($limit)
            ->map(function ($item) {
                $item['memory']->touchUsage();
                return $item['memory'];
            });
    }

    /**
     * Calculate the final score for a memory based on multiple factors.
     */
    private function calculateScore(Memory $memory): float
    {
        // Base score is the confidence
        $score = $memory->confidence;

        // Apply recency decay
        $ageInDays = $memory->last_seen_at->diffInDays(now());
        $halfLifeDays = config("memory.ttl_days.{$memory->ttl_category}") / 2;
        $recencyFactor = exp(-$ageInDays / $halfLifeDays);
        $score *= $recencyFactor;

        // Apply frequency boost (capped at 2.0)
        $frequencyBoost = 1 + min(log1p($memory->usage_count), 1.0);
        $score *= $frequencyBoost;

        // Apply scope boost
        $scopeBoosts = [
            Memory::SCOPE_CONVERSATION => 1.4,
            Memory::SCOPE_USER => 1.2,
            Memory::SCOPE_ACCOUNT => 1.0,
        ];
        $score *= ($scopeBoosts[$memory->scope] ?? 1.0);

        // Clamp final score to [0,1]
        return max(0, min(1, $score));
    }

    /**
     * Calculate expiry timestamp based on TTL class.
     */
    private function calculateExpiry(?string $ttlClass): ?Carbon
    {
        if ($ttlClass === Memory::TTL_LEGAL) {
            return null;
        }

        $ttlDays = config("memory.ttl_days.{$ttlClass}");
        return now()->addDays($ttlDays);
    }

    /**
     * Redact PII from memory value using configured rules.
     */
    private function redactPII(array $value): array
    {
        $redacted = $value;
        $piiRules = config('memory.pii_rule_set', []);

        foreach ($piiRules as $rule) {
            $redacted = $this->applyPIIRule($redacted, $rule);
        }

        return $redacted;
    }

    /**
     * Apply a single PII redaction rule recursively through an array.
     */
    private function applyPIIRule(array $data, array $rule): array
    {
        array_walk_recursive($data, function(&$value) use ($rule) {
            if (!is_string($value)) {
                return;
            }

            // Apply regex pattern if defined
            if (!empty($rule['pattern'])) {
                $value = preg_replace($rule['pattern'], '[REDACTED]', $value);
            }

            // Check for exact matches if defined
            if (!empty($rule['matches'])) {
                foreach ($rule['matches'] as $match) {
                    $value = str_ireplace($match, '[REDACTED]', $value);
                }
            }
        });

        return $data;
    }

    /**
     * Build provenance string from available context.
     */
    private function buildProvenance(?string $emailMessageId, ?string $threadId, array $meta): string
    {
        $parts = [];

        if ($emailMessageId) {
            $parts[] = "email:{$emailMessageId}";
        }
        if ($threadId) {
            $parts[] = "thread:{$threadId}";
        }
        if (!empty($meta['prompt_key'])) {
            $parts[] = "prompt:{$meta['prompt_key']}";
        }
        if (!empty($meta['model'])) {
            $parts[] = "model:{$meta['model']}";
        }

        return implode(';', $parts);
    }
}
