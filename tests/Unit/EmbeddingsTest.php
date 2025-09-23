<?php

namespace Tests\Unit;

use App\Services\Embeddings;
use Tests\TestCase;

class EmbeddingsTest extends TestCase
{
    public function test_assert_correct_dimension_ok(): void
    {
        config()->set('llm.embeddings.dim', 4);
        $svc = new Embeddings(['dim' => 4]);
        $svc->assertCorrectDimension([0.1, 0.2, 0.3, 0.4]);
        $this->assertTrue(true);
    }

    public function test_assert_correct_dimension_throws_on_mismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        config()->set('llm.embeddings.dim', 4);
        $svc = new Embeddings(['dim' => 4]);
        $svc->assertCorrectDimension([0.1, 0.2]);
    }
}


