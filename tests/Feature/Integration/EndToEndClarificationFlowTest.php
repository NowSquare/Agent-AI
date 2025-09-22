<?php

namespace Tests\Feature\Integration;

use App\Jobs\ProcessInboundEmail;
use App\Mail\ActionClarificationMail;
use App\Models\Account;
use App\Models\Action;
use App\Models\EmailInboundPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EndToEndClarificationFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete end-to-end flow from email to clarification to final response.
     */
    public function test_end_to_end_clarification_flow(): void
    {
        // Arrange
        Queue::fake();
        Mail::fake();
        Bus::fake();

        $account = Account::factory()->create();
        $payload = EmailInboundPayload::factory()->create([
            'encrypted_data' => json_encode([
                'message_id' => 'e2e-test-789',
                'subject' => 'End-to-end clarification test',
                'from_email' => 'user@example.com',
                'from_name' => 'Test User',
                'text_body' => 'Can you help me with something?',
                'html_body' => '<p>Can you help me with something?</p>',
                'headers' => [
                    ['Name' => 'From', 'Value' => 'Test User <user@example.com>'],
                ],
            ]),
        ]);

        // Mock MCP to return medium confidence (0.6)
        $this->mockMcpTool('App\Mcp\Tools\ActionInterpretationTool', [
            'action_type' => 'info_request',
            'parameters' => ['question' => 'Can you help me with something?'],
            'confidence' => 0.6, // Medium confidence - should trigger clarification
            'needs_clarification' => false,
        ]);

        // Act - Process the inbound email
        ProcessInboundEmail::dispatch($payload->id);

        // Assert Phase 1: Email processed, clarification email sent
        Mail::assertQueued(ActionClarificationMail::class, 1);

        $action = Action::first();
        $this->assertEquals('awaiting_confirmation', $action->status);
        $this->assertEquals('info_request', $action->type);

        // Phase 2: User confirms via signed URL
        $confirmUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'action.confirm.show',
            ['action' => $action->id],
            now()->addHours(72)
        );

        $response = $this->get($confirmUrl);
        $response->assertViewIs('action.confirm');

        // Phase 3: User submits confirmation
        $response = $this->post(\Illuminate\Support\Facades\URL::signedRoute(
            'action.confirm',
            ['action' => $action->id],
            now()->addHours(72)
        ));

        $response->assertJson(['success' => true]);

        // Assert Phase 4: Action is dispatched and processed
        Bus::assertDispatched(\App\Services\ActionDispatcher::class);

        $action->refresh();
        $this->assertEquals('processing', $action->status);
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
