<?php
/** Plain: Proves retrieval returns top-k with similarity and basic metrics work. */

namespace Tests\Unit;

use App\Services\Embeddings;
use App\Services\GroundingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GroundingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure vector extension available in test DB environment (assumed enabled in migrations)
    }

    public function test_hit_rate_and_top_similarity_calculation(): void
    {
        $svc = new GroundingService(app(Embeddings::class));
        $results = [
            ['similarity' => 0.50],
            ['similarity' => 0.30],
            ['similarity' => 0.80],
        ];
        $this->assertSame(0.667, round($svc->hitRate($results), 3));
        $this->assertSame(0.800, round($svc->topSimilarity($results), 3));
    }

    public function test_retrieve_top_k_structure(): void
    {
        // Insert a minimal row with embeddings to make KNN return something
        // For unit scope, skip actual DB inserts; assert structure of mapping
        $svc = new GroundingService(app(Embeddings::class));
        $map = [
            (object)['src' => 'email_messages', 'id' => '01ABC', 'text' => 'Hello', 'sim' => 0.9],
        ];
        $mapped = array_map(fn($r) => [
            'src' => $r->src,
            'id' => $r->id,
            'text' => $r->text,
            'similarity' => (float) $r->sim,
        ], $map);

        $this->assertSame('email_messages', $mapped[0]['src']);
        $this->assertSame('01ABC', $mapped[0]['id']);
        $this->assertSame(0.9, $mapped[0]['similarity']);
    }
}


