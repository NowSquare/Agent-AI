<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgentMetricsCommandTest extends TestCase
{
    public function test_agent_metrics_runs(): void
    {
        $this->artisan('agent:metrics --limit=1')
            ->assertExitCode(0);
    }
}


