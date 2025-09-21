<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller as BaseController;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class VerifyController extends BaseController
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|string|exists:auth_challenges,id',
            'code' => 'required|string|size:6|regex:/^\d{6}$/',
        ]);

        $challengeId = $request->input('challenge_id');
        $code = $request->input('code');

        // Rate limiting: 10 per 15 minutes per identifier
        $identifierKey = 'auth-verify:challenge:' . $challengeId;

        if (RateLimiter::tooManyAttempts($identifierKey, 10)) {
            throw ValidationException::withMessages([
                'code' => 'Too many verification attempts. Please try again later.',
            ]);
        }

        RateLimiter::increment($identifierKey, 15 * 60); // 15 minutes

        $challenge = $this->authService->verifyChallenge($challengeId, $code);

        if (!$challenge) {
            throw ValidationException::withMessages([
                'code' => 'Invalid or expired code.',
            ]);
        }

        // Log the user in
        $user = $challenge->userIdentity->user;
        Auth::login($user);

        return response()->json([
            'authenticated' => true,
            'user_id' => $user->id,
            'message' => 'Successfully authenticated.',
        ]);
    }
}
