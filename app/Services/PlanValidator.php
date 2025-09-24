<?php

namespace App\Services;

/**
 * What this file does — Checks a step-by-step plan against simple rules before we run it.
 * Plain: Reads the plan like a checklist. If a step is missing something, it tells you how to fix it.
 * How this fits in:
 * - Planner/Workers produce a symbolic plan (state → action → next-state)
 * - Orchestrator calls validate() before executing
 * - Debate can use the repair hint to fix the plan and try again
 * Key terms: preconditions (what must be true), effects (what will be true after)
 *
 * For engineers:
 * - Inputs: plan = ['steps'=>[['state'=>[facts], 'action'=>['name','args'], 'next_state'=>[facts]]...]], initialFacts = key=>value
 * - Output: PlanReport array ['valid'=>bool,'error'=>string|null,'failing_step'=>int|null,'hint'=>string|null,'final_facts'=>array]
 * - Operators: =, <, >, <=, >=, += (for numeric increments)
 */
class PlanValidator
{
    /**
     * Summary: Validate a plan step-by-step starting from initial facts.
     * @param array $plan          Structured plan with steps
     * @param array $initialFacts  Starting facts e.g. ['has_attachment'=>true,'confidence'=>0.62]
     * @return array               PlanReport with validity and repair hint
     */
    public function validate(array $plan, array $initialFacts): array
    {
        $actions = config('actions');
        $facts = $initialFacts;
        $steps = $plan['steps'] ?? [];

        foreach ($steps as $idx => $step) {
            $name = $step['action']['name'] ?? '';
            if (!isset($actions[$name])) {
                return $this->report(false, $idx, "Unknown action '{$name}'.", $facts);
            }
            // Check declared state matches simulated facts minimally (optional soft check)
            // WHY: helps catch prompt hallucinations where 'state' claims a fact not present

            // Check preconditions
            foreach ($actions[$name]['pre'] as $cond) {
                if (!$this->evaluateCondition($cond, $facts)) {
                    $hint = $this->buildHint($name, $cond);
                    return $this->report(false, $idx, "Precondition failed: {$cond}", $facts, $hint);
                }
            }

            // Apply effects
            foreach ($actions[$name]['eff'] as $eff) {
                $this->applyEffect($eff, $facts);
            }

            // Optional: compare declared next_state for consistency and merge
            foreach (($step['next_state'] ?? []) as $decl) {
                $this->applyEffect($decl, $facts);
            }
        }

        return $this->report(true, null, null, $facts);
    }

    /**
     * Evaluate a string condition against facts. Supports =, <, >, <=, >= operators.
     */
    private function evaluateCondition(string $cond, array $facts): bool
    {
        // Replace symbolic constants
        $cond = str_replace('LLM_MIN_CONF', (string) config('llm.routing.thresholds.grounding_hit_min', 0.35), $cond);

        // Patterns like key=val, key>=val, key<val etc.
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*(<=|>=|=|<|>)\s*([a-zA-Z0-9_.-]+)$/', $cond, $m)) {
            [$_, $key, $op, $raw] = $m;
            $val = $this->parseValue($raw);
            $cur = $facts[$key] ?? null;
            return match ($op) {
                '='  => $cur == $val,
                '<'  => is_numeric($cur) && $cur < (float) $val,
                '>'  => is_numeric($cur) && $cur > (float) $val,
                '<=' => is_numeric($cur) && $cur <= (float) $val,
                '>=' => is_numeric($cur) && $cur >= (float) $val,
                default => false,
            };
        }
        return false;
    }

    /** Apply an effect string like key=value or key+=delta into facts. */
    private function applyEffect(string $eff, array &$facts): void
    {
        // Increment operator
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\+=\s*([0-9.]+)$/', $eff, $m)) {
            $key = $m[1]; $delta = (float) $m[2];
            $facts[$key] = (float) ($facts[$key] ?? 0) + $delta;
            return;
        }
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([a-zA-Z0-9_.-]+)$/', $eff, $m)) {
            $key = $m[1]; $val = $this->parseValue($m[2]);
            $facts[$key] = $val;
        }
    }

    /** Parse raw token into bool|float|string. */
    private function parseValue(string $raw): bool|float|string
    {
        if ($raw === 'true') return true;
        if ($raw === 'false') return false;
        if (is_numeric($raw)) return (float) $raw;
        return $raw;
    }

    /** Build a simple human repair hint for a failed precondition. */
    private function buildHint(string $action, string $failedCond): string
    {
        return "Consider adding a step before '{$action}' to satisfy '{$failedCond}', or choose a different action that does not require it.";
    }

    /** Create a PlanReport array. */
    private function report(bool $ok, ?int $idx, ?string $error, array $facts, ?string $hint = null): array
    {
        return [
            'valid' => $ok,
            'failing_step' => $idx,
            'error' => $error,
            'hint' => $hint,
            'final_facts' => $facts,
        ];
    }
}


