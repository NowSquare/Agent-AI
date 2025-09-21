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
        // Validate HTTP Basic Auth credentials
        if (!$this->validateBasicAuth($request)) {
            Log::warning('Postmark webhook authentication failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->getContent();

        // Store encrypted payload
        $payloadId = $this->storePayload($payload, $request);

        // Queue processing job
        ProcessInboundEmail::dispatch($payloadId);

        return response()->json([
            'queued' => true,
            'payload_id' => $payloadId,
        ]);
    }

    private function validateBasicAuth(Request $request): bool
    {
        $expectedUser = config('services.postmark.webhook_user');
        $expectedPass = config('services.postmark.webhook_pass');

        if (!$expectedUser || !$expectedPass) {
            Log::error('Postmark webhook credentials not configured');
            return false;
        }

        $providedUser = $request->getUser();
        $providedPass = $request->getPassword();

        return hash_equals($expectedUser, $providedUser ?? '')
            && hash_equals($expectedPass, $providedPass ?? '');
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
