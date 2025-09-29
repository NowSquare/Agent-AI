<?php

namespace Tests\Feature;

use App\Console\Commands\InboundSimulate;
use App\Models\EmailInboundPayload;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebhookInboundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for inbound pipeline tests.');
        }
    }
    public function test_rfc_reply_headers_threading(): void
    {
        Artisan::call('migrate:fresh', ['--no-interaction' => true]);

        // Seed base thread
        Artisan::call('inbound:simulate', ['--file' => 'tests/fixtures/inbound_quote_attachments.json']);
        // Post a reply referencing previous MessageID via RFC headers
        Artisan::call('inbound:simulate', ['--file' => 'tests/fixtures/inbound_reply_rfc_headers.json']);

        $this->assertDatabaseCount('email_messages', 2);

        $first = EmailMessage::orderBy('created_at')->first();
        $second = EmailMessage::orderBy('created_at', 'desc')->first();
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->thread_id, $second->thread_id, 'Reply should be threaded to original');
    }

    public function test_non_english_language_detection_path(): void
    {
        Artisan::call('migrate:fresh', ['--no-interaction' => true]);
        Artisan::call('inbound:simulate', ['--file' => 'tests/fixtures/inbound_non_english_es_no_attachments.json']);

        $this->assertDatabaseHas('email_inbound_payloads', []);
        $this->assertDatabaseHas('email_messages', ['direction' => 'inbound']);
    }

    public function test_csv_attachment_ingestion(): void
    {
        Artisan::call('migrate:fresh', ['--no-interaction' => true]);
        Artisan::call('inbound:simulate', ['--file' => 'tests/fixtures/inbound_csv_attachment_clean.json']);

        $msg = EmailMessage::latest('created_at')->first();
        $this->assertNotNull($msg);
        $this->assertGreaterThanOrEqual(1, $msg->attachments()->count());
    }

    public function test_corrupted_base64_attachment_graceful_handling(): void
    {
        Artisan::call('migrate:fresh', ['--no-interaction' => true]);
        Artisan::call('inbound:simulate', ['--file' => 'tests/fixtures/inbound_corrupted_base64_attachment.json']);

        $this->assertDatabaseHas('email_inbound_payloads', []);
        // Message may still be created; attachments may be zero or marked failed depending on pipeline
        $msg = EmailMessage::latest('created_at')->first();
        $this->assertNotNull($msg);
    }
}


