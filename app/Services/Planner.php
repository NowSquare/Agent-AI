<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Thread;

class Planner
{
    /**
     * Produce a minimal multi-agent plan from an email/task.
     * Returns an associative array: ['tasks' => [...], 'deps' => [...]]
     */
    public function plan(Action $action, Thread $thread): array
    {
        $question = (string)($action->payload_json['question'] ?? '');

        // Minimal deterministic plan: two worker drafts with optional dependency graph
        $tasks = [
            [
                'id' => 'worker_grounded',
                'capability' => 'grounded_answer',
                'description' => 'Draft grounded answer using retrieved evidence',
            ],
            [
                'id' => 'worker_synth',
                'capability' => 'synth_answer',
                'description' => 'Draft synthetic answer leveraging reasoning',
            ],
        ];

        $deps = []; // Parallel by default

        return [
            'goal' => $this->inferGoal($question),
            'tasks' => $tasks,
            'deps' => $deps,
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


