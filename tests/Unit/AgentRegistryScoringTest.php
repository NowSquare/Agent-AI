<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\Agent;
use App\Services\AgentRegistry;

class AgentRegistryScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_topk_prefers_capability_and_reliability_over_cost(): void
    {
        $account = Account::factory()->create();

        $a = Agent::create([
            'account_id' => $account->id,
            'name' => 'HighSkillHighCost',
            'role' => 'Expert',
            'capabilities_json' => [
                'keywords' => ['pgvector','embedding','postgres'],
                'expertise' => ['information_retrieval'],
            ],
            'cost_hint' => 800,
            'reliability' => 0.9,
        ]);

        $b = Agent::create([
            'account_id' => $account->id,
            'name' => 'LowSkillLowCost',
            'role' => 'Generalist',
            'capabilities_json' => [
                'keywords' => ['cooking'],
            ],
            'cost_hint' => 100,
            'reliability' => 0.7,
        ]);

        $task = [ 'description' => 'Retrieve pgvector embeddings from Postgres' ];

        $registry = app(AgentRegistry::class);
        $ranked = $registry->topKForTask($account, $task, 2);

        $this->assertCount(2, $ranked);
        $this->assertEquals('HighSkillHighCost', $ranked[0]['agent']->name, 'Capability + reliability should outweigh cost.');
    }
}


