<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Thread;

/**
 * What this file does — Creates a simple task plan and emits symbolic steps.
 * Plain: Breaks the job into steps and shows the state before/after each step.
 * How this fits in:
 * - Orchestrator uses this output to allocate workers and validate the plan
 * - Debate can repair this plan when validation fails
 */
class Planner
{
    /**
     * Produce a minimal multi-agent plan from an email/task.
     * Returns an associative array: ['tasks' => [...], 'deps' => [...]]
     */
    public function plan(Action $action, Thread $thread): array
    {
        $question = (string)($action->payload_json['question'] ?? '');

        // Minimal deterministic plan: two worker drafts (grounded + synth)
        $tasks = [
            [ 'id' => 'worker_grounded', 'capability' => 'grounded_answer', 'description' => 'Draft grounded answer using retrieved evidence' ],
            [ 'id' => 'worker_synth', 'capability' => 'synth_answer', 'description' => 'Draft synthetic answer leveraging reasoning' ],
        ];

        $deps = []; // Parallel by default

        // Symbolic plan (state → action → next_state)
        $symbolic = [
            'steps' => [
                [
                    'state' => ['received=true','scanned=false','extracted=false','text_available=false','summary_ready=false','confidence=0.5'],
                    'action' => ['name' => 'Classify', 'args' => []],
                    'next_state' => ['classified=true'],
                ],
                [
                    'state' => ['classified=true','retrieval_done=false'],
                    'action' => ['name' => 'Retrieve', 'args' => []],
                    'next_state' => ['retrieval_done=true'],
                ],
            ],
        ];

        return [
            'goal' => $this->inferGoal($question),
            'tasks' => $tasks,
            'deps' => $deps,
            'plan' => $symbolic,
        ];
    }

    private function inferGoal(string $question): string
    {
        $q = strtolower($question);
        if (str_contains($q, 'plan') || str_contains($q, 'organize')) {
            return 'Planning and organization';
        }
        if (str_contains($q, 'schedule') || str_contains($q, 'book')) {
            return 'Scheduling and booking';
        }
        if (str_contains($q, 'find') || str_contains($q, 'search')) {
            return 'Information gathering';
        }
        return 'General assistance';
    }
}


