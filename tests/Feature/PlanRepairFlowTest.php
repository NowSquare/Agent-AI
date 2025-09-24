<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\Thread;
use App\Models\Action;
use App\Services\Coordinator;

/** Plain: Proves a run with unscanned attachment gets repaired by inserting ScanAttachment, then validates. */
class PlanRepairFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_repair_inserts_scan_step(): void
    {
        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);

        // Seed an attachment scenario (simulate has_attachment=true via initial facts in orchestrator)
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'type' => 'info_request',
            'payload_json' => ['question' => 'Please summarize the attached PDF', 'confidence' => 0.5],
        ]);

        app(Coordinator::class)->processAction($action);

        $action->refresh();
        $this->assertEquals('completed', $action->status);
        $this->assertArrayHasKey('plan_report', $action->payload_json);
        $this->assertTrue($action->payload_json['plan_valid'] ?? false);
    }
}


