<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChallengeRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ChallengeController extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an authentication challenge request.
     */
    public function __invoke(ChallengeRequest $request): JsonResponse
    {
        $email = Str::lower($request->input('email'));
        
        // Rate limiting key includes both email and IP for better security
        $key = Str::transliterate($email . '|' . $request->ip());
        
        // Check rate limit: 5 attempts per 15 minutes
        $limiter = RateLimiter::attempt(
            key: "auth-challenge:{$key}",
            maxAttempts: 5,
            callback: function () use ($email) {
                // Create and send challenge
                $challenge = $this->auth->createChallenge($email);
                
                return [
                    'message' => __('auth.challenge.sent'),
                    'expires_in' => $challenge->expires_at->diffInMinutes(now()),
                ];
            },
            decaySeconds: 15 * 60 // 15 minutes
        );
        
        if (! $limiter) {
            return response()->json([
                'message' => __('auth.challenge.rate_limited', [
                    'minutes' => ceil(RateLimiter::availableIn("auth-challenge:{$key}") / 60),
                ]),
            ], 429);
        }
        
        return response()->json($limiter);
    }
}