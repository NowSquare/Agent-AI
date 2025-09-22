<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundEmail;
use App\Mail\ActionOptionsMail;
use App\Models\Account;
use App\Models\Action;
use App\Models\EmailInboundPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClarificationLowConfidenceOptionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that low confidence (<0.50) triggers options email.
     */
    public function test_low_confidence_sends_options_email(): void
    {
        // Arrange
        Queue::fake();
        Mail::fake();

        $account = Account::factory()->create();
        $payload = EmailInboundPayload::factory()->create([
            'encrypted_data' => json_encode([
                'message_id' => 'test-message-456',
                'subject' => 'Test options request',
                'from_email' => 'user@example.com',
                'from_name' => 'Test User',
                'text_body' => 'I need some help with a recipe',
                'html_body' => '<p>I need some help with a recipe</p>',
                'headers' => [
                    ['Name' => 'From', 'Value' => 'Test User <user@example.com>'],
                ],
            ]),
        ]);

        // Mock MCP to return low confidence
        $this->mockMcpTool('App\Mcp\Tools\ActionInterpretationTool', [
            'action_type' => 'info_request',
            'parameters' => ['question' => 'I need some help with a recipe'],
            'confidence' => 0.42, // Low confidence
            'needs_clarification' => false,
        ]);

        // Act
        ProcessInboundEmail::dispatch($payload->id);

        // Assert
        // Should send options email
        Mail::assertQueued(ActionOptionsMail::class, 1);

        // Action should be awaiting input
        $action = Action::first();
        $this->assertEquals('awaiting_input', $action->status);
        $this->assertEquals('info_request', $action->type);
    }

    /**
     * Test that selecting an option updates the action and can proceed.
     */
    public function test_option_selection_updates_action(): void
    {
        // Arrange
        $account = Account::factory()->create();
        $thread = \App\Models\Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'status' => 'awaiting_input',
            'type' => 'info_request',
            'payload_json' => ['question' => 'I need some help with a recipe'],
        ]);

        // Act - Select Italian pasta option
        $response = $this->get(\Illuminate\Support\Facades\URL::signedRoute(
            'action.options.choose',
            ['action' => $action->id, 'key' => 'italian_pasta'],
            now()->addHours(72)
        ));

        // Assert
        $response->assertStatus(200);
        $response->assertViewIs('action.confirm');

        $action->refresh();
        $this->assertEquals('awaiting_confirmation', $action->status);
        $this->assertEquals('Can you provide an Italian pasta recipe for 4 people?', $action->payload_json['question']);
        $this->assertEquals('options_email', $action->payload_json['clarified_via']);
    }

    /**
     * Helper to mock MCP tool responses.
     */
    private function mockMcpTool(string $toolClass, array $response): void
    {
        $mock = \Mockery::mock($toolClass);
        $mockResponse = \Laravel\Mcp\Response::json($response);
        $mock->shouldReceive('handle')->andReturn($mockResponse);

        $this->app->bind($toolClass, fn () => $mock);
    }
}
