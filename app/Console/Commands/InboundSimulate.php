<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInboundEmail;
use App\Models\EmailInboundPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

/**
 * What this file does — Simulates an inbound Postmark webhook using a local fixture.
 * Plain: Pretends an email with two PDFs arrived, so you can run the demo.
 * How this fits in:
 * - Writes an encrypted payload row then runs the normal job
 * - Ensures default account and contact are created by existing flow
 */
class InboundSimulate extends Command
{
    /** @var string */
    protected $signature = 'inbound:simulate {--file=tests/fixtures/inbound_postmark.json}';

    /** @var string */
    protected $description = 'Inject a local inbound JSON fixture and run ProcessInboundEmail';

    public function handle(): int
    {
        $path = $this->option('file');
        if (! File::exists($path)) {
            $this->error("Fixture not found: {$path}");

            return 1;
        }
        $json = File::get($path);
        $payload = json_decode($json, true);
        if (! $payload) {
            $this->error('Invalid JSON fixture.');

            return 1;
        }

        // Store as EmailInboundPayload using existing model (encrypted ciphertext handled in job)
        $row = EmailInboundPayload::create([
            'ciphertext' => Crypt::encryptString(json_encode($payload)),
            'received_at' => now(),
            'purge_after' => now()->addDays(30),
        ]);

        // Kick the normal job synchronously for determinism
        dispatch_sync(new ProcessInboundEmail($row->id));

        $threadId = optional(\App\Models\EmailMessage::latest('created_at')->first())->thread_id;
        $from = $payload['From'] ?? '';
        $email = (new class
        {
            public function email($s)
            {
                return preg_match('/<([^>]+)>/', $s, $m) ? strtolower(trim($m[1])) : strtolower(trim($s));
            }
        })->email($from);

        $this->info("Simulated inbound created. thread_id={$threadId} contact_email={$email}");

        return 0;
    }
}
