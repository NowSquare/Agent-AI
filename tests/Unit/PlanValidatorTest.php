<?php

namespace Tests\Unit;

use App\Services\PlanValidator;
use Tests\TestCase;

class PlanValidatorTest extends TestCase
{
    public function test_valid_plan_passes(): void
    {
        $validator = new PlanValidator;

        $plan = [
            'steps' => [
                ['state' => ['received=true'], 'action' => ['name' => 'Classify', 'args' => []], 'next_state' => []],
                ['state' => ['classified=true'], 'action' => ['name' => 'Retrieve', 'args' => []], 'next_state' => []],
                ['state' => ['has_attachment=true', 'clamav_ready=true', 'scanned=false'], 'action' => ['name' => 'ScanAttachment', 'args' => []], 'next_state' => []],
                ['state' => ['scanned=true', 'extracted=false'], 'action' => ['name' => 'ExtractText', 'args' => []], 'next_state' => []],
                ['state' => ['retrieval_done=true', 'text_available=true'], 'action' => ['name' => 'GroundedAnswer', 'args' => []], 'next_state' => []],
                ['state' => ['summary_ready=true', 'confidence>=LLM_MIN_CONF'], 'action' => ['name' => 'SendReply', 'args' => []], 'next_state' => []],
            ],
        ];

        $initialFacts = [
            'received' => true,
            'classified' => false,
            'retrieval_done' => false,
            'has_attachment' => true,
            'clamav_ready' => true,
            'scanned' => false,
            'extracted' => false,
            'text_available' => false,
            'summary_ready' => false,
            'confidence' => 0.30,
        ];

        $report = $validator->validate($plan, $initialFacts);

        $this->assertTrue($report['valid'] ?? false, 'Plan should validate');
        $this->assertArrayHasKey('final_facts', $report);
        $this->assertEquals(true, $report['final_facts']['retrieval_done'] ?? null);
        $this->assertEquals(true, $report['final_facts']['text_available'] ?? null);
        $this->assertEquals(true, $report['final_facts']['summary_ready'] ?? null);
        $this->assertGreaterThanOrEqual((float) config('llm.routing.thresholds.grounding_hit_min', 0.35), (float) $report['final_facts']['confidence']);
    }

    public function test_unmet_precondition_fails_with_hint(): void
    {
        $validator = new PlanValidator;

        $plan = [
            'steps' => [
                ['state' => ['extracted=false'], 'action' => ['name' => 'ExtractText', 'args' => []], 'next_state' => []],
            ],
        ];

        $initialFacts = [
            'scanned' => false,
            'extracted' => false,
        ];

        $report = $validator->validate($plan, $initialFacts);

        $this->assertFalse($report['valid'] ?? true, 'Plan should be invalid');
        $this->assertNotEmpty($report['error'] ?? '', 'Should include error message');
        $this->assertNotEmpty($report['hint'] ?? '', 'Should include a repair hint');
        $this->assertSame(0, $report['failing_step']);
    }

    public function test_increment_operator_applies_to_confidence(): void
    {
        $validator = new PlanValidator;

        $plan = [
            'steps' => [
                ['state' => ['retrieval_done=true', 'text_available=true'], 'action' => ['name' => 'GroundedAnswer', 'args' => []], 'next_state' => []],
            ],
        ];

        $initialFacts = [
            'retrieval_done' => true,
            'text_available' => true,
            'confidence' => 0.20,
        ];

        $report = $validator->validate($plan, $initialFacts);

        $this->assertTrue($report['valid'] ?? false);
        $this->assertEquals(0.30, round((float) $report['final_facts']['confidence'], 2));
    }
}
