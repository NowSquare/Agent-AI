<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MultiAgentMetrics;

/**
 * agent:metrics — Print a concise summary of recent multi-agent runs.
 *
 * Options:
 *  --since=ISO8601   Only include runs since this timestamp (default: 7 days ago)
 *  --limit=INT       Maximum number of runs to include (default: 20)
 */
class AgentMetrics extends Command
{
    protected $signature = 'agent:metrics {--since=} {--limit=20}';
    protected $description = 'Show multi-agent run metrics summary';

    public function handle(MultiAgentMetrics $metrics): int
    {
        $since = $this->option('since');
        $limit = (int) $this->option('limit');
        $summary = $metrics->compute($since, $limit);

        $this->info('Multi-Agent Metrics');
        $this->line('Since: ' . $summary['since']);
        $this->line('Runs: ' . $summary['count']);
        $this->line('Max Rounds: ' . $summary['rounds_max']);
        $this->line('Groundedness % (Critic >= min): ' . number_format($summary['groundedness_pct'] * 100, 1) . '%');
        $this->newLine();
        $this->info('Role Activity');
        foreach ($summary['roles'] as $role => $row) {
            $this->line(sprintf('- %s: count=%d, latency_ms=%d', $role, $row['count'], $row['latency_ms']));
        }
        $this->newLine();
        $this->info('Top Agents by Wins');
        foreach ($summary['win_distribution'] as $name => $wins) {
            $this->line(sprintf('- %s: %d', $name, $wins));
        }

        return self::SUCCESS;
    }
}


