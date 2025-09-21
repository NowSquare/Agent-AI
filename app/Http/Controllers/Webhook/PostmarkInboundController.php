<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundEmail;
use App\Models\EmailInboundPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PostmarkInboundController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Postmark-Signature');

        // Validate HMAC signature
        if (!$this->validateSignature($payload, $signature)) {
            Log::warning('Postmark webhook signature validation failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Store encrypted payload
        $payloadId = $this->storePayload($payload, $request);

        // Queue processing job
        ProcessInboundEmail::dispatch($payloadId);

        return response()->json([
            'queued' => true,
            'payload_id' => $payloadId,
        ]);
    }

    private function validateSignature(string $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $secret = config('services.postmark.webhook_secret');
        if (!$secret) {
            Log::error('Postmark webhook secret not configured');
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function storePayload(string $payload, Request $request): string
    {
        $payloadId = Str::ulid()->toString();

        EmailInboundPayload::create([
            'id' => $payloadId,
            'provider' => 'postmark',
            'ciphertext' => Crypt::encryptString($payload),
            'meta_json' => [
                'headers' => [
                    'user_agent' => $request->userAgent(),
                    'content_type' => $request->header('Content-Type'),
                ],
            ],
            'signature_verified' => true,
            'remote_ip' => $request->ip(),
            'content_length' => strlen($payload),
            'received_at' => now(),
            'purge_after' => now()->addDays(30),
        ]);

        return $payloadId;
    }
}
