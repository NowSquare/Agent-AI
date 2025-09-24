<?php
/** Plain: Proves ModelRouter picks GROUNDED vs SYNTH based on thresholds. */

namespace Tests\Unit;

use App\Services\ModelRouter;
use Tests\TestCase;

class ModelRouterTest extends TestCase
{
    public function test_grounded_when_hit_rate_at_threshold_and_tokens_below_synth(): void
    {
        config()->set('llm.routing.thresholds.grounding_hit_min', 0.35);
        config()->set('llm.routing.thresholds.synth_complexity_tokens', 1200);

        $router = new ModelRouter();
        $role = $router->chooseRole(100, 0.35, 0.9);
        $this->assertSame('GROUNDED', $role);
    }

    public function test_synth_when_tokens_at_threshold(): void
    {
        config()->set('llm.routing.thresholds.grounding_hit_min', 0.35);
        config()->set('llm.routing.thresholds.synth_complexity_tokens', 1200);

        $router = new ModelRouter();
        $role = $router->chooseRole(1200, 0.90, 0.95);
        $this->assertSame('SYNTH', $role);
    }

    public function test_synth_when_hit_rate_below_threshold(): void
    {
        config()->set('llm.routing.thresholds.grounding_hit_min', 0.35);
        config()->set('llm.routing.thresholds.synth_complexity_tokens', 1200);

        $router = new ModelRouter();
        $role = $router->chooseRole(100, 0.34, 0.20);
        $this->assertSame('SYNTH', $role);
    }
}


