<?php

namespace App\Jobs;

use App\Models\EmailInboundPayload;
use App\Services\ReplyCleaner;
use App\Services\ThreadResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessWebhookPayload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $payload,
        public string $remoteIp,
        public string $userAgent,
        public ?string $contentType,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReplyCleaner $replyCleaner, ThreadResolver $threadResolver): void
    {
        Log::info('Processing webhook payload', [
            'payload_length' => strlen($this->payload),
            'remote_ip' => $this->remoteIp,
        ]);

        // Store the encrypted payload
        $actualPayloadId = $this->storePayload();

        // Process the email
        $this->processEmail($actualPayloadId, $replyCleaner, $threadResolver);
    }

    private function storePayload(): string
    {
        $payloadId = Str::ulid()->toString();

        DB::beginTransaction();

        try {
            $payload = EmailInboundPayload::create([
                'id' => $payloadId,
                'provider' => 'postmark',
                'ciphertext' => Crypt::encryptString($this->payload),
                'meta_json' => [
                    'headers' => [
                        'user_agent' => $this->userAgent,
                        'content_type' => $this->contentType,
                    ],
                ],
                'signature_verified' => true,
                'remote_ip' => $this->remoteIp,
                'content_length' => strlen($this->payload),
                'received_at' => now(),
                'purge_after' => now()->addDays(30),
            ]);

            // Force commit the transaction
            DB::commit();

            Log::info('Webhook payload stored and committed', [
                'payload_id' => $payloadId,
                'actual_id' => $payload->id,
                'created_at' => $payload->created_at,
            ]);

            // Double-check it exists
            $exists = EmailInboundPayload::find($payload->id);
            if (!$exists) {
                throw new \Exception('Payload was not found after commit');
            }

            return $payload->id;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to store webhook payload', [
                'error' => $e->getMessage(),
                'payload_id' => $payloadId,
            ]);
            throw $e;
        }
    }

    private function processEmail(string $actualPayloadId, ReplyCleaner $replyCleaner, ThreadResolver $threadResolver): void
    {
        // Dispatch the email processing job with the actual stored payload ID
        ProcessInboundEmail::dispatch($actualPayloadId);
    }
}
