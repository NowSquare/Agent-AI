<?php

namespace Tests\Unit;

use App\Mcp\Tools\LanguageDetectTool;
use App\Mcp\Tools\MemoryExtractTool;
use App\Mcp\Tools\ThreadSummarizeTool;
use App\Services\LlmClient;
use Illuminate\Validation\ValidationException;
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

    public function test_language_detect_tool_throws_on_invalid_shape(): void
    {
        $this->expectException(ValidationException::class);

        $mock = m::mock(LlmClient::class);
        $mock->shouldReceive('json')->once()->andReturn([
            'lang' => 'en', // wrong key
        ]);
        $this->app->instance(LlmClient::class, $mock);

        $tool = $this->app->make(LanguageDetectTool::class);
        $tool->runReturningArray('Hello world');
    }
}


