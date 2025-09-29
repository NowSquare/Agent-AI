<?php

namespace Tests\Unit;

use App\Mcp\Tools\ActionInterpretationTool;
use App\Mcp\Tools\HttpHeadTool;
use App\Mcp\Tools\LanguageDetectTool;
use App\Mcp\Tools\MemoryExtractTool;
use App\Mcp\Tools\ThreadSummarizeTool;
use App\Services\LlmClient;
use App\Services\UrlGuard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Mockery as m;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_language_detect_tool_validates_and_returns_schema(): void
    {
        $mock = m::mock(LlmClient::class);
        $mock->shouldReceive('json')->once()->andReturn([
            'language' => 'en',
            'confidence' => 0.98,
        ]);

        $this->app->instance(LlmClient::class, $mock);

        $tool = $this->app->make(LanguageDetectTool::class);
        $result = $tool->runReturningArray('Hello world');

        $this->assertSame('en', $result['language']);
        $this->assertEquals(0.98, $result['confidence']);
    }

    public function test_thread_summarize_tool_validates_and_returns_schema(): void
    {
        $mock = m::mock(LlmClient::class);
        $mock->shouldReceive('json')->once()->andReturn([
            'summary' => 'Short summary',
            'key_entities' => ['Alice', 'Bob'],
            'open_questions' => ['When?'],
        ]);

        $this->app->instance(LlmClient::class, $mock);

        $tool = $this->app->make(ThreadSummarizeTool::class);
        $result = $tool->runReturningArray('en_US', 'Alice: hi', '');

        $this->assertSame('Short summary', $result['summary']);
        $this->assertIsArray($result['key_entities']);
        $this->assertIsArray($result['open_questions']);
    }

    public function test_memory_extract_tool_validates_and_returns_schema(): void
    {
        $mock = m::mock(LlmClient::class);
        $mock->shouldReceive('json')->once()->andReturn([
            'items' => [[
                'key' => 'preference_pasta',
                'value' => ['value' => 'carbonara'],
                'scope' => 'conversation',
                'ttl_category' => 'volatile',
                'confidence' => 0.9,
                'provenance' => 'email_message_id:abc',
            ]],
        ]);

        $this->app->instance(LlmClient::class, $mock);

        $tool = $this->app->make(MemoryExtractTool::class);
        $result = $tool->runReturningArray('en_US', 'I like carbonara');

        $this->assertIsArray($result['items']);
        $this->assertArrayHasKey('key', $result['items'][0]);
    }

    public function test_language_detect_tool_normalizes_keys(): void
    {
        // Model returns non-canonical key 'lang'; tool should normalize to 'language'.
        $mock = m::mock(LlmClient::class);
        $mock->shouldReceive('json')->once()->andReturn([
            'lang' => 'en',
        ]);
        $this->app->instance(LlmClient::class, $mock);

        $tool = $this->app->make(LanguageDetectTool::class);
        $result = $tool->runReturningArray('Hello world');

        $this->assertSame('en', $result['language']);
        $this->assertArrayHasKey('confidence', $result); // defaulted
    }

    public function test_action_interpretation_tool_uses_tool_enforced_json_and_validates(): void
    {
        $mock = m::mock(LlmClient::class);
        $mock->shouldReceive('json')->once()->andReturn([
            'action_type' => 'approve',
            'parameters' => ['reason' => 'ok'],
            'scope_hint' => null,
            'confidence' => 0.8,
            'needs_clarification' => false,
            'clarification_prompt' => null,
        ]);
        $this->app->instance(LlmClient::class, $mock);

        $tool = $this->app->make(ActionInterpretationTool::class);
        $result = $tool->runReturningArray('Sounds good');

        $this->assertSame('approve', $result['action_type']);
        $this->assertSame(false, $result['needs_clarification']);
        $this->assertIsArray($result['parameters']);
        $this->assertGreaterThanOrEqual(0, $result['confidence']);
        $this->assertLessThanOrEqual(1, $result['confidence']);
    }

    public function test_http_head_tool_blocks_private_ip_via_url_guard(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tool = $this->app->make(HttpHeadTool::class);

        $req = new Request(['url' => 'http://127.0.0.1/internal']);
        // Real UrlGuard should throw here
        UrlGuard::assertSafeUrl('http://127.0.0.1/internal');

        $tool->handle($req);
    }
}


