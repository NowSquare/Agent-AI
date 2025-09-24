<?php
/** Plain: Proves tie-break picks grounded candidate (has evidence) before cheaper/older. */

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DebateCoordinator;

class DebateTieBreakTest extends TestCase
{
    public function test_tie_break_prefers_grounded_then_cost_then_oldest(): void
    {
        $debate = new DebateCoordinator();

        $candidates = [
            ['id' => '01HAAA', 'text' => 'A', 'score' => 0.80, 'evidence' => [], 'cost_hint' => 500, 'reliability' => 0.8],
            ['id' => '01HAAB', 'text' => 'B', 'score' => 0.80, 'evidence' => ['e1'], 'cost_hint' => 700, 'reliability' => 0.8],
            ['id' => '01HAAA1', 'text' => 'C', 'score' => 0.80, 'evidence' => [], 'cost_hint' => 300, 'reliability' => 0.8],
        ];

        $result = $debate->runKRounds($candidates, rounds: 1);

        $this->assertNotEmpty($result['winner']);
        // Candidate B has evidence -> higher groundedness; should win tie on groundedness before cost
        $this->assertEquals('01HAAB', $result['winner']['id']);
    }
}


