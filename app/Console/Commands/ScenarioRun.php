<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * What this file does — Runs an end‑to‑end demo: migrate fresh, simulate inbound, show metrics.
 * Plain: Resets the DB, pretends an email with two PDFs arrived, runs the agents, and prints what to check.
 */
class ScenarioRun extends Command
{
    /** @var string */
    protected $signature = 'scenario:run';

    /** @var string */
    protected $description = 'Reset DB, simulate inbound email with PDFs, run agents, and show a checklist';

    public function handle(): int
    {
        $this->call('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);

        $this->call('inbound:simulate', ['--file' => 'tests/fixtures/inbound_postmark.json']);

        // Optional: embeddings backfill if you have a command for it
        if (class_exists(\App\Console\Commands\EmbeddingsBackfill::class)) {
            $this->call('embeddings:backfill', ['--quiet' => true]);
        }

        // Quick agent metrics summary (if present)
        if (class_exists(\App\Console\Commands\AgentMetrics::class)) {
            $this->call('agent:metrics', ['--limit' => 5]);
        }

        $this->line('');
        $this->info('What to check (Plain):');
        $this->line('- Activity → latest thread shows Planner, Workers, Critic, Arbiter steps.');
        $this->line('- Plan panel: Valid ✓ (or first failing step + hint after auto‑repair).');
        $this->line('- Debate ran K rounds; winner listed; near‑top retained as minority when close.');
        $this->line('- A typed Decision memory saved with provenance (see Memories).');
        $this->line('- Log in as the sender email to see only your own thread and full trace.');

        return 0;
    }
}


