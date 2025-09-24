<?php

namespace App\Services;

class DebateCoordinator
{
    /**
     * Run K rounds of debate and return a decision.
     * candidates: [ ["id"=>string, "text"=>string, "score"=>float, "evidence"=>array], ... ]
     * evidence: array of retrieved items, optional
     */
    public function runKRounds(array $candidates, array $evidence = [], int $rounds = 2): array
    {
        $votes = [];
        $current = $candidates;

        for ($r = 1; $r <= $rounds; $r++) {
            // Deterministic scoring: prefer higher score; tie-breaker = longer evidence list, then lexical id
            usort($current, function ($a, $b) {
                $sa = (float)($a['score'] ?? 0);
                $sb = (float)($b['score'] ?? 0);
                if ($sa === $sb) {
                    $ea = is_countable($a['evidence'] ?? []) ? count($a['evidence']) : 0;
                    $eb = is_countable($b['evidence'] ?? []) ? count($b['evidence']) : 0;
                    if ($ea === $eb) {
                        return strcmp((string)($b['id'] ?? ''), (string)($a['id'] ?? '')); // stable
                    }
                    return $eb <=> $ea;
                }
                return $sb <=> $sa;
            });

            $leader = $current[0] ?? null;
            if ($leader) {
                $votes[] = [
                    'round' => $r,
                    'winner_id' => $leader['id'] ?? null,
                    'vote_score' => round((float)($leader['score'] ?? 0), 2),
                    'reason' => $this->buildReason($leader),
                ];
            }

            // Optional: prune to top 2 for next round
            $current = array_slice($current, 0, min(2, count($current)));
        }

        $final = end($votes) ?: ['winner_id' => null, 'vote_score' => 0.0, 'reason' => ''];
        $winner = null;
        foreach ($candidates as $c) {
            if (($c['id'] ?? null) === ($final['winner_id'] ?? null)) { $winner = $c; break; }
        }

        return [
            'winner' => $winner,
            'votes' => $votes,
            'reasons' => array_map(fn($v) => $v['reason'], $votes),
        ];
    }

    private function buildReason(array $candidate): string
    {
        $parts = [];
        $parts[] = 'higher base score';
        $evCount = is_countable($candidate['evidence'] ?? []) ? count($candidate['evidence']) : 0;
        if ($evCount > 0) { $parts[] = "more evidence (".$evCount.")"; }
        return 'Chosen due to ' . implode(' and ', $parts) . '.';
    }
}
