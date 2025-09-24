<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\Thread;
use App\Models\Action;
use App\Services\AgentRegistry;
use App\Services\Coordinator;
use App\Models\AgentStep;
use App\Models\Memory;

class MultiAgentProtocolTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_workers_and_debate_and_curation_flow(): void
    {
        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);

        // Ensure we have at least two agents
        app(AgentRegistry::class)->createSampleAgents($account);

        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'type' => 'info_request',
            'payload_json' => ['question' => 'What is pgvector and how is it used?'],
        ]);

        // Run through coordinator (which invokes MultiAgentOrchestrator for complex cases)
        app(Coordinator::class)->processAction($action);

        $action->refresh();
        $this->assertEquals('completed', $action->status);
        $this->assertNotEmpty($action->payload_json['final_response'] ?? '');

        // Steps logged: Planner, Worker(s), Arbiter
        $roles = AgentStep::pluck('agent_role')->filter()->values()->all();
        $this->assertContains('Planner', $roles);
        $this->assertContains('Worker', $roles);
        $this->assertContains('Arbiter', $roles);

        // Memory curated
        $mem = Memory::where('thread_id', $thread->id)
            ->where('key', 'agent_decision_summary')
            ->first();
        $this->assertNotNull($mem);
        $this->assertIsArray($mem->value_json);
        $this->assertNotEmpty($mem->value_json['final_answer'] ?? '');
    }
}


