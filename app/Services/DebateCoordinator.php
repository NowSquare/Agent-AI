<?php
/**
 * What this file does — Scores and narrows candidates over K rounds, allowing a minority report.
 * Plain: The checker compares drafts, keeps the best few, and explains why.
 * How this fits in:
 * - Orchestrator calls it after workers produce drafts
 * - Logs Critic rounds and the Arbiter’s final pick
 * - Uses weights from config/agents.php
 * Key terms: groundedness, completeness, risk, minority report (near‑top kept)
 *
 * For engineers:
 * - Inputs: candidates[], evidence[], rounds K
 * - Outputs: winner, votes[], reasons[]
 * - Tie-breaks: higher groundedness → lower cost → oldest
 */

namespace App\Services;

/**
 * DebateCoordinator runs a structured, multi-round evaluation over candidate drafts.
 *
 * Responsibilities:
 *  - For K rounds, keep the best candidates and allow a minority report if within epsilon
 *  - Score groundedness, completeness, risk (stubbed heuristics here; real scoring can plug-in)
 *  - Aggregate votes using configured weights (critic/worker/arbiter)
 *  - Deterministic tie-breaks: prefer higher groundedness, then lower expected cost, then oldest
 */
class DebateCoordinator
{
    /**
     * Run K rounds of debate, returning the winner, vote log and reasons.
     * Each candidate is an associative array including: id, text, score, evidence[], reliability, cost_hint
     */
    public function runKRounds(array $candidates, array $evidence = [], int $rounds = 2): array
    {
        $eps = (float) config('agents.minority_epsilon', 0.05);
        $weights = config('agents.vote.weights');

        $votes = [];
        $current = $candidates;
        for ($r = 1; $r <= $rounds; $r++) {
            // Critic scoring per candidate
            $scored = array_map(function ($c) {
                $g = $this->estimateGroundedness($c);
                $comp = $this->estimateCompleteness($c);
                $risk = $this->estimateRisk($c);
                $criticScore = max(0.0, min(1.0, 0.6*$g + 0.3*$comp + 0.1*(1-$risk)));
                return $c + [
                    'groundedness' => $g,
                    'completeness' => $comp,
                    'risk' => $risk,
                    'critic_score' => $criticScore,
                ];
            }, $current);

            // Sort by critic_score desc
            usort($scored, fn($a,$b) => ($b['critic_score'] <=> $a['critic_score']));

            // Minority report retention if within epsilon of top
            $top = $scored[0]['critic_score'] ?? 0.0;
            $kept = array_filter($scored, fn($c) => ($top - $c['critic_score']) <= $eps);
            $kept = array_values($kept);

            // Weighted aggregation using critic and worker self-score (llm confidence)
            foreach ($kept as $k) {
                $workerSelf = (float)($k['score'] ?? 0.0);
                $agg = ($weights['critic_weight'] * (float)$k['critic_score'])
                     + ($weights['worker_weight'] * $workerSelf)
                     + ($weights['arbiter_weight'] * 0.0); // arbiter weight used at final pick
                $votes[] = [
                    'round' => $r,
                    'winner_id' => $k['id'],
                    'vote_score' => round($agg, 3),
                    'reason' => $this->buildRoundReason($k),
                ];
            }

            // Prepare next round candidate set
            $current = $kept;
        }

        // Final decision: aggregate all votes by candidate
        $byId = [];
        foreach ($votes as $v) {
            $byId[$v['winner_id']] = ($byId[$v['winner_id']] ?? 0) + $v['vote_score'];
        }

        // Build final list with attributes for tie-breaks
        $finalList = [];
        foreach ($current as $c) {
            $finalList[] = $c + [ 'agg_score' => $byId[$c['id']] ?? 0.0 ];
        }

        // Choose winner using agg_score then tie-breaks
        usort($finalList, function($a,$b){
            if (($b['agg_score'] <=> $a['agg_score']) !== 0) return $b['agg_score'] <=> $a['agg_score'];
            // Tie-break 1: higher groundedness
            if (($b['groundedness'] <=> $a['groundedness']) !== 0) return $b['groundedness'] <=> $a['groundedness'];
            // Tie-break 2: lower expected cost
            if ((($a['cost_hint'] ?? 999999) <=> ($b['cost_hint'] ?? 999999)) !== 0) return ($a['cost_hint'] ?? 999999) <=> ($b['cost_hint'] ?? 999999);
            // Tie-break 3: oldest by id (ulid lexicographic)
            return strcmp((string)$a['id'], (string)$b['id']);
        });

        $winner = $finalList[0] ?? null;

        return [
            'winner' => $winner,
            'votes' => $votes,
            'reasons' => array_map(fn($v) => $v['reason'], $votes),
        ];
    }

    // --- Heuristic scorers (replaceable hooks) ---

    private function estimateGroundedness(array $c): float
    {
        // Stub: prefer candidates with any evidence and higher worker confidence
        $base = !empty($c['evidence']) ? 0.6 : 0.4;
        $conf = (float)($c['score'] ?? 0.0);
        return max(0.0, min(1.0, $base + 0.4*$conf));
    }

    private function estimateCompleteness(array $c): float
    {
        // Stub: longer text => more complete up to a cap
        $len = strlen((string)($c['text'] ?? ''));
        return max(0.0, min(1.0, $len / 1500));
    }

    private function estimateRisk(array $c): float
    {
        // Stub: higher risk if low reliability
        $rel = max(0.0, min(1.0, (float)($c['reliability'] ?? 0.8)));
        return 1.0 - $rel; // lower reliability => higher risk
    }

    private function buildRoundReason(array $k): string
    {
        return sprintf(
            'critic=%.2f, grounded=%.2f, complete=%.2f, risk=%.2f',
            (float)$k['critic_score'],
            (float)$k['groundedness'],
            (float)$k['completeness'],
            (float)$k['risk']
        );
    }
}
