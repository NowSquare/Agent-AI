<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class VerifyController extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle a login verification request.
     */
    public function __invoke(VerifyRequest $request): JsonResponse
    {
        $email = Str::lower($request->input('email'));
        $code = $request->input('code');
        $remember = $request->boolean('remember', false);

        // Rate limiting key includes both email and IP for better security
        $key = Str::transliterate($email . '|' . $request->ip());

        // Check rate limit: 10 attempts per 15 minutes
        $limiter = RateLimiter::attempt(
            key: "auth-verify:{$key}",
            maxAttempts: 10,
            callback: function () use ($email, $code, $remember) {
                // Verify challenge and authenticate user
                $user = $this->auth->verifyChallenge($email, $code, $remember);

                if (!$user) {
                    return response()->json([
                        'message' => __('auth.verify.invalid_code'),
                    ], 422);
                }

                // Log the user in
                Auth::login($user, $remember);

                return [
                    'message' => __('auth.verify.success'),
                    'redirect' => route('dashboard'),
                ];
            },
            decaySeconds: 15 * 60 // 15 minutes
        );

        if (!$limiter) {
            return response()->json([
                'message' => __('auth.verify.rate_limited', [
                    'minutes' => ceil(RateLimiter::availableIn("auth-verify:{$key}") / 60),
                ]),
            ], 429);
        }

        return response()->json($limiter);
    }
}