<?php

namespace App\Services;

final class ModelRouter
{
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


