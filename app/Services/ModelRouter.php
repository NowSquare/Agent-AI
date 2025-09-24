<?php
/**
 * What this file does — Chooses which role/model to use for a request.
 * Plain: Picks small vs big model depending on length and retrieval quality.
 * How this fits in:
 * - Called by AgentProcessor to decide CLASSIFY/GROUNDED/SYNTH
 * - Reads thresholds from config/llm.php
 * - Keeps app defaults simple and explainable
 * Key terms:
 * - hitRate: share of retrieved matches above a similarity bar
 * - topSim: highest similarity score among retrieved items
 *
 * For engineers:
 * - Inputs: token estimate, hitRate, topSim
 * - Output: role string used to resolve provider+model
 * - No side effects; pure decision logic
 */

namespace App\Services;

/**
 * Purpose: Encapsulate routing thresholds and role selection.
 * Responsibilities:
 * - Pick role based on token length and retrieval score
 * - Map role to provider/model/tool flags
 * Collaborators:
 * - GroundingService provides hitRate/topSim
 * - config/llm.php supplies thresholds and role bindings
 */
final class ModelRouter
{
    /**
     * Summary: Decide which role to use based on tokens and retrieval score.
     * @param int   $tokensIn  Approximate input tokens
     * @param float $hitRate   Fraction of retrieval results above bar
     * @param float $topSim    Peak similarity (not used directly here)
     * @return string          'CLASSIFY'|'GROUNDED'|'SYNTH'
     */
    public function chooseRole(int $tokensIn, float $hitRate, float $topSim): string
    {
        $thresholds = config('llm.routing.thresholds');
        $hitMin = (float) ($thresholds['grounding_hit_min'] ?? 0.35);
        $synthTokens = (int) ($thresholds['synth_complexity_tokens'] ?? 1200);

        if ($tokensIn >= $synthTokens) {
            return 'SYNTH';
        }
        if ($hitRate >= $hitMin) {
            return 'GROUNDED';
        }
        return 'SYNTH';
    }

    /**
     * Summary: Read provider/model/tool flags for a role from config.
     * @param string $role  Role key
     * @return array{provider:string,model:string,tools:bool,reasoning:bool}
     */
    public function resolveProviderModel(string $role): array
    {
        $roles = config('llm.routing.roles');
        $cfg = $roles[$role] ?? [];
        return [
            'provider'  => $cfg['provider'] ?? 'ollama',
            'model'     => $cfg['model'] ?? 'qwen3:14b',
            'tools'     => (bool) ($cfg['tools'] ?? true),
            'reasoning' => (bool) ($cfg['reasoning'] ?? false),
        ];
    }
}


