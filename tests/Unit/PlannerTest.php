<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Planner;
use App\Models\Action;
use App\Models\Thread;
use App\Models\Account;

class PlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_returns_parallel_worker_tasks(): void
    {
        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'type' => 'info_request',
            'payload_json' => ['question' => 'Please find info on pgvector'],
        ]);

        $planner = new Planner();
        $plan = $planner->plan($action, $thread);

        $this->assertIsArray($plan);
        $this->assertArrayHasKey('tasks', $plan);
        $this->assertCount(2, $plan['tasks']);
        $ids = array_column($plan['tasks'], 'id');
        $this->assertContains('worker_grounded', $ids);
        $this->assertContains('worker_synth', $ids);
        $this->assertArrayHasKey('deps', $plan);
        $this->assertSame([], $plan['deps']);
    }
}


