<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrchestrationPlanValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scenario_produces_plan_report_arbiter_and_decision_memory(): void
    {
        $this->artisan('scenario:run')->assertExitCode(0);

        // Run the queue to allow orchestrator jobs to finish where applicable
        // Use stop-when-empty to avoid hanging
        $this->artisan('queue:work', ['--queue' => 'default,attachments', '--stop-when-empty' => true]);

        // Plan report (Critic step) present
        $critic = \App\Models\AgentStep::query()
            ->where('agent_role', 'Critic')
            ->orderByDesc('created_at')
            ->first();
        $this->assertNotNull($critic, 'Critic step expected');
        $report = $critic->output_json['report'] ?? null;
        $this->assertIsArray($report, 'Plan report array expected');
        $this->assertArrayHasKey('valid', $report);

        // Arbiter decision present
        $arb = \App\Models\AgentStep::query()
            ->where('agent_role', 'Arbiter')
            ->orderByDesc('created_at')
            ->first();
        $this->assertNotNull($arb, 'Arbiter step expected');
        $this->assertArrayHasKey('winner_id', $arb->output_json ?? [], 'Arbiter output should include winner_id');
        $this->assertNotNull($arb->vote_score, 'vote_score should be set');

        // Decision memory saved with provenance
        $mem = \App\Models\Memory::query()->where('type', 'Decision')->latest('created_at')->first();
        $this->assertNotNull($mem, 'Memory expected');
        $prov = $mem->provenance_ids ?? [];
        $this->assertIsArray($prov, 'provenance_ids should be array');
        $this->assertNotEmpty($prov, 'provenance_ids should not be empty');
    }
}
