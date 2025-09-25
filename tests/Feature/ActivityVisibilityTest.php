<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AgentStep;
use App\Models\Contact;
use App\Models\ContactLink;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_thread_steps(): void
    {
        // Seed default data via scenario to create a thread and steps
        $this->artisan('scenario:run')->assertExitCode(0);

        $thread = Thread::query()->latest('created_at')->firstOrFail();
        $account = $thread->account;

        // Create a user and link to the contact used in the thread
        $fromEmail = $thread->emailMessages()->latest('created_at')->value('from_email');
        $contact = Contact::firstOrCreate(['account_id' => $account->id, 'email' => $fromEmail]);
        $user = User::factory()->create(['email' => $fromEmail]);
        ContactLink::firstOrCreate(['contact_id' => $contact->id, 'user_id' => $user->id]);

        $this->be($user);

        $resp = $this->get(route('activity.index'));
        $resp->assertStatus(200);

        // Ensure at least one step for this thread is visible in the view
        $resp->assertSee(e($thread->subject));

        // Create another thread under same account but different contact
        $otherThread = Thread::factory()->create(['account_id' => $account->id, 'subject' => 'Other Thread']);
        AgentStep::factory()->create(['account_id' => $account->id, 'thread_id' => $otherThread->id]);

        // The index page should not list the other thread subject for this user
        $resp2 = $this->get(route('activity.index'));
        $resp2->assertStatus(200);
        $resp2->assertDontSee('Other Thread');
    }
}
