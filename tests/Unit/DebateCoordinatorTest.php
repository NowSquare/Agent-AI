<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DebateCoordinator;

class DebateCoordinatorTest extends TestCase
{
    public function test_deterministic_winner_by_score_then_evidence_then_id(): void
    {
        $debate = new DebateCoordinator();

        $candidates = [
            ['id' => 'b', 'text' => 'B', 'score' => 0.80, 'evidence' => [1]],
            ['id' => 'a', 'text' => 'A', 'score' => 0.80, 'evidence' => [1,2]],
            ['id' => 'c', 'text' => 'C', 'score' => 0.75, 'evidence' => []],
        ];

        $result = $debate->runKRounds($candidates, rounds: 2);

        $this->assertNotEmpty($result['winner']);
        $this->assertEquals('a', $result['winner']['id']);
        $this->assertCount(2, $result['votes']);
        $this->assertStringContainsString('Chosen due to', $result['reasons'][0]);
    }
}


