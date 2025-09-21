<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Queue the entire webhook processing to avoid request context issues
        ProcessWebhookPayload::dispatch($payload, $request->ip(), $request->userAgent(), $request->header('Content-Type'));

        return response()->json([
            'queued' => true,
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

}
