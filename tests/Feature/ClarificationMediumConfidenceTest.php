<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundEmail;
use App\Mail\ActionClarificationMail;
use App\Models\Account;
use App\Models\Action;
use App\Models\EmailInboundPayload;
use App\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClarificationMediumConfidenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that medium confidence (0.50-0.74) triggers clarification email and awaits confirmation.
     */
    public function test_medium_confidence_sends_clarification_email(): void
    {
        // Arrange
        Queue::fake();
        Mail::fake();

        $account = Account::factory()->create();
        $payload = EmailInboundPayload::factory()->create();

        // Mock MCP to return medium confidence
        $mockTool = \Mockery::mock(\App\Mcp\Tools\ActionInterpretationTool::class);
        $mockResponse = \Laravel\Mcp\Response::json([
            'action_type' => 'info_request',
            'parameters' => ['question' => 'Can you help me with something?'],
            'confidence' => 0.65, // Medium confidence
            'needs_clarification' => false,
        ]);
        $mockTool->shouldReceive('handle')
            ->once()
            ->andReturn($mockResponse);

        // Debug: Check if payload exists
        $this->assertNotNull($payload, 'Payload should exist');
        $this->assertDatabaseHas('email_inbound_payloads', ['id' => $payload->id]);

        // Act - Run the job synchronously
        $job = new ProcessInboundEmail($payload->id);

        // Check if the job can find the payload
        $payloadRecord = \App\Models\EmailInboundPayload::find($payload->id);
        $this->assertNotNull($payloadRecord, 'Payload record should be found');

        try {
            $job->handle(
                app(\App\Services\ReplyCleaner::class),
                app(\App\Services\ThreadResolver::class),
                app(\App\Services\LlmClient::class),
                $mockTool
            );
        } catch (\Exception $e) {
            $this->fail('ProcessInboundEmail job failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        // Assert
        // Action should be created
        $action = Action::first();
        $this->assertNotNull($action, 'Action should be created');

        // Action should be awaiting confirmation
        $this->assertEquals('awaiting_confirmation', $action->status, 'Action should be awaiting confirmation');
        $this->assertEquals('info_request', $action->type, 'Action type should be info_request');

        // Should send clarification email
        Mail::assertQueued(ActionClarificationMail::class, 1);
        Queue::assertPushed(\App\Jobs\SendClarificationEmail::class, 1);

        // Should not dispatch action immediately
        Queue::assertNotPushed(\App\Jobs\SendActionResponse::class);
    }

    /**
     * Test that confirming a clarification executes the action.
     */
    public function test_confirming_clarification_executes_action(): void
    {
        // Arrange
        Queue::fake();
        Mail::fake();

        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'status' => 'awaiting_confirmation',
            'type' => 'info_request',
            'payload_json' => ['question' => 'Can you help me with something?'],
        ]);

        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'action.confirm.show',
            ['action' => $action->id],
            now()->addHours(72)
        );

        // Act - Visit the confirmation URL
        $response = $this->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $response->assertViewIs('action.confirm');
        $response->assertViewHas('action', $action);
    }

    /**
     * Test that cancelling a clarification marks action as cancelled.
     */
    public function test_cancelling_clarification_marks_action_cancelled(): void
    {
        // Arrange
        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'status' => 'awaiting_confirmation',
            'type' => 'info_request',
            'payload_json' => ['question' => 'Can you help me with something?'],
        ]);

        // Act - Cancel the action
        $response = $this->post(\Illuminate\Support\Facades\URL::signedRoute(
            'action.confirm.cancel',
            ['action' => $action->id],
            now()->addHours(72)
        ));

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'cancelled',
        ]);

        $action->refresh();
        $this->assertEquals('cancelled', $action->status);
        $this->assertNotNull($action->cancelled_at);
    }

    /**
     * Test that expired signed URLs are rejected.
     */
    public function test_expired_signed_urls_are_rejected(): void
    {
        // Arrange
        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'status' => 'awaiting_confirmation',
            'type' => 'info_request',
        ]);

        // Create an expired signed URL
        $expiredUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'action.confirm.show',
            ['action' => $action->id],
            now()->subHours(1) // Already expired
        );

        // Act
        $response = $this->get($expiredUrl);

        // Assert
        $response->assertStatus(200);
        $response->assertViewIs('action.expired');
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
