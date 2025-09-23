<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\AuthMagicLinkEmail;

class LoginController extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle a magic link login request.
     */
    public function magicLink(Request $request, string $token)
    {
        // Verify the token
        $user = $this->auth->verifyMagicLink($token);

        if (!$user) {
            return redirect()->route('auth.challenge.form')
                ->with('error', __('auth.magic_link.invalid'));
        }

        // Log the user in
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', __('auth.magic_link.success'));
    }

    /**
     * Send a magic link to the user.
     */
    public function sendMagicLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ]);

        $email = strtolower($request->input('email'));
        $url = $this->auth->createMagicLink($email);

        // Send magic link email
        Mail::to($email)->send(new AuthMagicLinkEmail($url));

        return response()->json([
            'message' => __('auth.magic_link.sent'),
        ]);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.challenge.form')
            ->with('success', __('auth.logout.success'));
    }
}