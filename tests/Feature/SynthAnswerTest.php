<?php

namespace Tests\Feature;

use App\Services\GroundingService;
use App\Services\ModelRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SynthAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_synth_selected_for_long_or_poor_matches(): void
    {
        config()->set('llm.routing.thresholds.synth_complexity_tokens', 1200);
        $ground = app(GroundingService::class);

        $text = str_repeat('long input ', 2000); // very long to force SYNTH by tokens
        $tokens = (int) ceil(str_word_count($text) / 0.75);
        $hit = 0.0; $topSim = 0.0; // simulate poor matches
        $router = new ModelRouter();
        $role = $router->chooseRole($tokens, $hit, $topSim);
        $this->assertSame('SYNTH', $role);
    }
}


