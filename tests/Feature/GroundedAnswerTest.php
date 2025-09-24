<?php
/** Plain: Proves answers use retrieval (GROUNDED) when hit-rate is high. */

namespace Tests\Feature;

use App\Models\Account;
use App\Models\EmailMessage;
use App\Models\Memory;
use App\Services\Embeddings;
use App\Services\GroundingService;
use App\Services\ModelRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroundedAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_grounded_role_selected_with_strong_matches(): void
    {
        config()->set('llm.embeddings.dim', 4);

        $account = Account::factory()->create();
        $email = EmailMessage::factory()->create([
            'body_text' => 'Invoice for July hours: total 1200 EUR from Anna',
        ]);

        // Minimal memory row
        $mem = Memory::create([
            'id' => (string) \Str::ulid(),
            'scope' => 'account',
            'scope_id' => $account->id,
            'key' => 'last_invoice_sender',
            'value_json' => 'Anna invoice July',
            'confidence' => 0.9,
            'ttl_category' => 'durable',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        // Backfill tiny embeddings deterministically
        $svc = app(Embeddings::class);
        $svc->storeEmailBodyEmbedding($email->id, [0.1,0.2,0.3,0.4]);
        $svc->storeMemoryEmbedding($mem->id, [0.1,0.2,0.3,0.4]);

        $query = 'Find the July invoice from Anna';
        // Simulate that embedText returns a very close vector (avoid external call)
        // We bypass embedText by directly using GroundingService with a pre-known query text;
        // since vectors are simple, the ranking should return these rows.
        $ground = app(GroundingService::class);
        $top = $ground->retrieveTopK($query, 8);
        $hit = $ground->hitRate($top);
        $topSim = $ground->topSimilarity($top);

        $router = new ModelRouter();
        $role = $router->chooseRole(50, $hit, $topSim);

        $this->assertNotEmpty($top);
        $this->assertSame('GROUNDED', $role);
    }
}


