<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller as BaseController;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends BaseController
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function magicLink(Request $request, string $token): RedirectResponse
    {
        $challenge = $this->authService->verifyMagicLink($token);

        if (!$challenge) {
            return redirect('/auth/challenge')->withErrors([
                'token' => 'Invalid or expired magic link.',
            ]);
        }

        // Log the user in
        $user = $challenge->userIdentity->user;
        Auth::login($user);

        // Redirect to dashboard
        return redirect('/dashboard')->with('success', 'Successfully signed in!');
    }
}
