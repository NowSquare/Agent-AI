<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PlanValidator;

/** Plain: Proves PlanValidator accepts valid plans and rejects invalid ones with a hint. */
class PlanValidatorTest extends TestCase
{
    public function test_accepts_valid_plan(): void
    {
        $validator = new PlanValidator();
        $plan = [
            'steps' => [
                [ 'state' => ['received=true','scanned=false'], 'action' => ['name'=>'ScanAttachment','args'=>[]], 'next_state' => ['scanned=true'] ],
                [ 'state' => ['scanned=true','extracted=false'], 'action' => ['name'=>'ExtractText','args'=>[]], 'next_state' => ['extracted=true','text_available=true'] ],
                [ 'state' => ['text_available=true'], 'action' => ['name'=>'Summarize','args'=>[]], 'next_state' => ['summary_ready=true'] ],
                [ 'state' => ['summary_ready=true','confidence=0.8'], 'action' => ['name'=>'SendReply','args'=>[]], 'next_state' => ['reply_ready=true'] ],
            ],
        ];
        $facts = ['received'=>true,'clamav_ready'=>true,'confidence'=>0.8];
        $report = $validator->validate($plan, $facts);
        $this->assertTrue($report['valid']);
    }

    public function test_rejects_unmet_precondition_with_hint(): void
    {
        $validator = new PlanValidator();
        $plan = [
            'steps' => [
                [ 'state' => ['extracted=false'], 'action' => ['name'=>'ExtractText','args'=>[]], 'next_state' => ['extracted=true'] ],
            ],
        ];
        $facts = ['received'=>true,'clamav_ready'=>true];
        $report = $validator->validate($plan, $facts);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty($report['hint']);
    }
}


