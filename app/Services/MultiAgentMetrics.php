<?php
/**
 * What this file does — Computes simple stats about recent multi‑agent runs.
 * Plain: A scoreboard that sums rounds, time, and who wins often.
 * How this fits in:
 * - Used by the agent:metrics command to print a quick health check
 * - Reads AgentRun and AgentStep; no writes
 * Key terms: groundedness % (Critic score ≥ threshold), win distribution
 */

namespace App\Services;

use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Agent;
use Illuminate\Support\Carbon;

/**
 * MultiAgentMetrics computes useful aggregates for recent multi-agent runs.
 *
 * Provided metrics per run window:
 *  - rounds (max round_no)
 *  - counts and tokens per role (if available)
 *  - groundedness proxy: share of Critic vote_score >= min_groundedness
 *  - time per role (latency_ms sums)
 *  - rework rate: average number of Worker drafts per task
 *  - win distribution per agent (approx by winner candidate_id → agent_id)
 */
class MultiAgentMetrics
{
    /** @return array<string,mixed> */
    public function compute(?string $sinceIso = null, int $limit = 20): array
    {
        $since = $sinceIso ? Carbon::parse($sinceIso) : now()->subDays(7);

        $runs = AgentRun::query()
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $summary = [
            'since' => $since->toIso8601String(),
            'count' => $runs->count(),
            'rounds_max' => 0,
            'roles' => [
                'Planner' => ['count' => 0, 'latency_ms' => 0],
                'Worker'  => ['count' => 0, 'latency_ms' => 0],
                'Critic'  => ['count' => 0, 'latency_ms' => 0],
                'Arbiter' => ['count' => 0, 'latency_ms' => 0],
            ],
            'groundedness_pct' => 0.0,
            'win_distribution' => [],
        ];

        $minG = (float) config('agents.min_groundedness', 0.6);
        $criticTotal = 0; $criticGrounded = 0;

        foreach ($runs as $run) {
            $roundMax = AgentStep::where('thread_id', $run->thread_id)->max('round_no') ?? 0;
            $summary['rounds_max'] = max($summary['rounds_max'], (int)$roundMax);

            $steps = AgentStep::where('thread_id', $run->thread_id)->get();
            foreach ($steps as $s) {
                $role = $s->agent_role ?? null;
                if ($role && isset($summary['roles'][$role])) {
                    $summary['roles'][$role]['count'] += 1;
                    $summary['roles'][$role]['latency_ms'] += (int)($s->latency_ms ?? 0);
                }

                if ($role === 'Critic') {
                    $criticTotal += 1;
                    $score = (float)($s->vote_score ?? 0.0);
                    if ($score >= $minG) { $criticGrounded += 1; }
                }

                if ($role === 'Arbiter' && isset($s->output_json['winner_id'])) {
                    $winnerTaskId = $s->output_json['winner_id'];
                    // Approximate: task id → agent via tasks table
                    $agentId = optional(\App\Models\Task::find($winnerTaskId))->agent_id;
                    if ($agentId) {
                        $summary['win_distribution'][$agentId] = ($summary['win_distribution'][$agentId] ?? 0) + 1;
                    }
                }
            }
        }

        $summary['groundedness_pct'] = $criticTotal > 0 ? round($criticGrounded / $criticTotal, 3) : 0.0;

        // Map agent IDs to names for readability
        $summary['win_distribution'] = collect($summary['win_distribution'])
            ->mapWithKeys(function ($wins, $agentId) {
                $name = optional(Agent::find($agentId))->name ?: $agentId;
                return [$name => $wins];
            })
            ->sortDesc()
            ->toArray();

        return $summary;
    }
}


