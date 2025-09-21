<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller as BaseController;
use App\Models\AuthChallenge;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ChallengeController extends BaseController
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|email|max:255',
        ]);

        $identifier = strtolower($request->input('identifier'));

        // Rate limiting: 5 per 15 minutes per identifier, 20/hour per IP
        $identifierKey = 'auth-challenge:identifier:' . $identifier;
        $ipKey = 'auth-challenge:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($identifierKey, 5)) {
            throw ValidationException::withMessages([
                'identifier' => 'Too many attempts. Please try again later.',
            ]);
        }

        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            throw ValidationException::withMessages([
                'identifier' => 'Too many attempts from this IP. Please try again later.',
            ]);
        }

        RateLimiter::increment($identifierKey, 15 * 60); // 15 minutes
        RateLimiter::increment($ipKey, 60 * 60); // 1 hour

        // Create or find user identity
        $userIdentity = UserIdentity::firstOrCreate([
            'type' => 'email',
            'identifier' => $identifier,
        ], [
            'user_id' => User::firstOrCreate([
                'email' => $identifier,
            ], [
                'name' => explode('@', $identifier)[0], // Simple name extraction
                'display_name' => explode('@', $identifier)[0],
                'locale' => 'en_US',
                'timezone' => 'Europe/Amsterdam',
                'status' => 'active',
            ])->id,
        ]);

        // Create auth challenge
        $challenge = $this->authService->createChallenge($userIdentity);

        return response()->json([
            'challenge_id' => $challenge->id,
            'message' => 'Check your email for a login code.',
        ]);
    }
}
