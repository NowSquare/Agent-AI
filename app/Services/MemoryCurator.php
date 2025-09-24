<?php

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
    public function persistOutcome(string $runId, Thread $thread, Account $account, string $finalAnswer, array $provenanceIds = []): ?Memory
    {
        $value = [
            'run_id' => $runId,
            'final_answer' => mb_substr($finalAnswer, 0, 2000),
            'provenance_ids' => array_values(array_unique($provenanceIds)),
        ];

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
