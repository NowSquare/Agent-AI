<?php
/**
 * What this file does — Handles passwordless login flow (challenge and verify).
 * Plain: Sends a code to your email and checks it when you enter it.
 * How this fits in:
 * - Creates Users on first verified login
 * - Links Users to Contacts via ContactLink
 * - Applies rate limits and expiry from config
 */

namespace App\Services;

use App\Models\User;
use App\Models\Contact;
use App\Models\AuthChallenge;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\AuthChallengeEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AuthService
{
    private ContactLinkService $contactLinks;

    public function __construct(ContactLinkService $contactLinks)
    {
        $this->contactLinks = $contactLinks;
    }

    /**
     * Create an authentication challenge for the given email.
     */
    public function createChallenge(string $email): AuthChallenge
    {
        // Normalize email
        $email = strtolower(trim($email));

        // Generate a 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create challenge record
        $challenge = new AuthChallenge([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
            'attempts' => 0,
            'meta_json' => ['ip' => request()->ip()],
        ]);
        $challenge->save();

        // Send challenge email
        Mail::to($email)->send(new AuthChallengeEmail($challenge));

        Log::info('Auth challenge created', [
            'email' => $email,
            'expires_at' => $challenge->expires_at,
            'ip' => request()->ip(),
        ]);

        return $challenge;
    }

    /**
     * Verify a challenge code and authenticate the user.
     */
    public function verifyChallenge(string $email, string $code, bool $remember = false): ?User
    {
        $email = strtolower(trim($email));

        // Find latest non-expired challenge
        $challenge = AuthChallenge::where('email', $email)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (!$challenge) {
            Log::warning('No valid challenge found', ['email' => $email]);
            return null;
        }

        // Increment attempts
        $challenge->attempts++;
        $challenge->save();

        // Verify code
        if (!hash_equals($challenge->code, $code)) {
            Log::warning('Invalid challenge code', [
                'email' => $email,
                'attempts' => $challenge->attempts,
            ]);
            return null;
        }

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => null,
                'display_name' => null,
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone'),
                'status' => 'active',
            ]
        );

        // Link to any matching contacts
        $this->contactLinks->linkUserToContacts($user);

        // Mark challenge as used
        $challenge->update([
            'used_at' => now(),
            'meta_json' => array_merge($challenge->meta_json ?? [], [
                'success' => true,
                'user_id' => $user->id,
            ]),
        ]);

        // Set remember token if requested
        if ($remember) {
            $user->setRememberToken(Str::random(60));
            $user->save();
        }

        Log::info('User authenticated via challenge', [
            'user_id' => $user->id,
            'email' => $email,
            'remember' => $remember,
        ]);

        return $user;
    }

    /**
     * Create a magic login link for a user.
     */
    public function createMagicLink(string $email): string
    {
        $email = strtolower(trim($email));
        
        // Generate a secure token
        $token = Str::random(64);
        
        // Store the token with a 60-minute expiry
        $challenge = new AuthChallenge([
            'email' => $email,
            'code' => $token, // Using code field for token
            'expires_at' => now()->addHour(),
            'meta_json' => [
                'type' => 'magic_link',
                'ip' => request()->ip(),
            ],
        ]);
        $challenge->save();

        // Generate signed URL
        $url = route('login.magic', ['token' => $token]);

        Log::info('Magic link created', [
            'email' => $email,
            'expires_at' => $challenge->expires_at,
        ]);

        return $url;
    }

    /**
     * Verify a magic login link token.
     */
    public function verifyMagicLink(string $token): ?User
    {
        // Find valid token
        $challenge = AuthChallenge::where('code', $token)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->whereJsonContains('meta_json->type', 'magic_link')
            ->first();

        if (!$challenge) {
            Log::warning('Invalid or expired magic link token');
            return null;
        }

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $challenge->email],
            [
                'name' => null,
                'display_name' => null,
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone'),
                'status' => 'active',
            ]
        );

        // Link to any matching contacts
        $this->contactLinks->linkUserToContacts($user);

        // Mark challenge as used
        $challenge->update([
            'used_at' => now(),
            'meta_json' => array_merge($challenge->meta_json ?? [], [
                'success' => true,
                'user_id' => $user->id,
            ]),
        ]);

        Log::info('User authenticated via magic link', [
            'user_id' => $user->id,
            'email' => $challenge->email,
        ]);

        return $user;
    }
}