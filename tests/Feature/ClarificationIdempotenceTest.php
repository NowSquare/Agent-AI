<?php

namespace Tests\Feature;

use App\Jobs\SendClarificationEmail;
use App\Jobs\SendOptionsEmail;
use App\Mail\ActionClarificationMail;
use App\Mail\ActionOptionsMail;
use App\Models\Account;
use App\Models\Action;
use App\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClarificationIdempotenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that re-sending clarification emails is idempotent.
     */
    public function test_clarification_email_idempotence(): void
    {
        // Arrange
        Mail::fake();

        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'status' => 'awaiting_confirmation',
            'type' => 'info_request',
            'payload_json' => ['question' => 'Test question'],
            'meta_json' => ['clarification_sent_at' => now()], // Already sent
        ]);

        // Act - Try to send again
        SendClarificationEmail::dispatch($action);

        // Assert - Should not send duplicate email
        Mail::assertNotQueued(ActionClarificationMail::class);
    }

    /**
     * Test that re-sending options emails is idempotent.
     */
    public function test_options_email_idempotence(): void
    {
        // Arrange
        Mail::fake();

        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'status' => 'awaiting_input',
            'type' => 'info_request',
            'payload_json' => ['question' => 'Test question'],
            'meta_json' => ['options_sent_at' => now()], // Already sent
        ]);

        // Act - Try to send again
        SendOptionsEmail::dispatch($action);

        // Assert - Should not send duplicate email
        Mail::assertNotQueued(ActionOptionsMail::class);
    }

    /**
     * Test that multiple job dispatches don't cause duplicate processing.
     */
    public function test_multiple_job_dispatches_are_idempotent(): void
    {
        // Arrange
        Mail::fake();

        $account = Account::factory()->create();
        $thread = Thread::factory()->create(['account_id' => $account->id]);
        $action = Action::factory()->create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'status' => 'awaiting_confirmation',
            'type' => 'info_request',
            'payload_json' => ['question' => 'Test question'],
        ]);

        // Act - Dispatch the same job multiple times
        SendClarificationEmail::dispatch($action);
        SendClarificationEmail::dispatch($action);
        SendClarificationEmail::dispatch($action);

        // Assert - Should send only one email
        Mail::assertQueued(ActionClarificationMail::class, 1);

        $action->refresh();
        $this->assertNotNull($action->meta_json['clarification_sent_at']);
    }
}
