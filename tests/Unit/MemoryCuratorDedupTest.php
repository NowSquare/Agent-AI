<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\MemoryCurator;
use App\Models\Account;
use App\Models\Thread;

/** Plain: Proves Decision memories are deduplicated by content hash. */
class MemoryCuratorDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deduplicates_by_content_hash(): void
    {
        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);

        $curator = app(MemoryCurator::class);
        $m1 = $curator->persistOutcome('run1', $thread, $account, 'Final decision text', ['e1','e2']);
        $m2 = $curator->persistOutcome('run2', $thread, $account, 'Final decision text', ['e1','e2']);

        $this->assertNotNull($m1);
        $this->assertEquals($m1->id, $m2->id, 'Duplicate outcomes should return the same memory row.');
    }
}


