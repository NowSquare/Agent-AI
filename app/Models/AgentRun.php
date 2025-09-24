<?php
/**
 * What this file does — Stores the shared state of a multi‑agent run.
 * Plain: A small notebook that remembers the plan and the current round.
 * How this fits in:
 * - Orchestrator creates/updates this during a run
 * - Metrics read it to summarize recent runs
 * - No user sees this directly; UI reads steps instead
 * Key terms: state (json), round_no (int)
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purpose: Represent a run’s blackboard state.
 * Responsibilities:
 * - Persist plan/round in json
 * - Link to account/thread
 * Collaborators: MultiAgentOrchestrator, MultiAgentMetrics
 */
class AgentRun extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id', 'thread_id', 'state', 'round_no',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'round_no' => 'integer',
        ];
    }

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function thread(): BelongsTo { return $this->belongsTo(Thread::class); }
}


