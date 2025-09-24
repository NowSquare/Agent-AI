<?php
/**
 * What this file does — Saves a compact “Decision” memory with provenance and dedup.
 * Plain: Stores a short note about what we decided, without duplicates.
 * How this fits in:
 * - Called after the Arbiter picks a winner
 * - Lets future runs recall outcomes and evidence
 * - Works with MemoryService TTL/decay
 * Key terms: provenance_ids, content_hash
 */

namespace App\Services;

use App\Models\Thread;
use App\Models\Account;
use App\Models\Memory;

class MemoryCurator
{
    public function __construct(private MemoryService $memoryService) {}

    /**
     * Persist final outcome as a curated memory with provenance ids.
     */
    /**
     * Persist a compact semantic Decision memory with provenance and dedup hash.
     * Duplicate outcomes return the existing memory instead of creating a new row.
     */
    public function persistOutcome(string $runId, Thread $thread, Account $account, string $finalAnswer, array $provenanceIds = []): ?Memory
    {
        $hash = substr(hash('sha256', $finalAnswer . '|' . implode(',', $provenanceIds)), 0, 16);

        $value = [
            'run_id' => $runId,
            'final_answer' => mb_substr($finalAnswer, 0, 2000),
            'provenance_ids' => array_values(array_unique($provenanceIds)),
            'type' => \App\Enums\MemoryType::Decision->value,
            'content_hash' => $hash,
        ];

        // Dedup by stable content hash (if existing, return it)
        $existing = Memory::byScope(Memory::SCOPE_CONVERSATION, $thread->id)
            ->where('key', 'agent_decision_summary')
            ->where('value_json->content_hash', $hash)
            ->first();
        if ($existing) {
            return $existing;
        }

        return $this->memoryService->writeGate(
            Memory::SCOPE_CONVERSATION,
            $thread->id,
            key: 'agent_decision_summary',
            value: $value,
            confidence: 0.9,
            ttlClass: Memory::TTL_SEASONAL,
            emailMessageId: null,
            threadId: $thread->id,
            meta: [
                'model' => 'curator',
                'prompt_key' => 'curate_decision',
            ]
        );
    }
}
