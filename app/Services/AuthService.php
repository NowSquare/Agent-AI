<?php

namespace App\Services;

use App\Mail\AuthChallengeEmail;
use App\Models\AuthChallenge;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthService
{
    public function createChallenge(UserIdentity $userIdentity): AuthChallenge
    {
        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Generate secure token for magic link
        $token = Str::random(64);

        // Create challenge record
        $challenge = AuthChallenge::create([
            'user_identity_id' => $userIdentity->id,
            'identifier' => $userIdentity->identifier,
            'channel' => 'email',
            'code_hash' => Hash::make($code),
            'token' => $token,
            'expires_at' => now()->addMinutes(15), // 15 minutes
            'ip' => request()->ip(),
        ]);

        // Send email
        Mail::to($userIdentity->identifier)->send(
            new AuthChallengeEmail($challenge, $code)
        );

        return $challenge;
    }

    public function verifyChallenge(string $challengeId, string $code): ?AuthChallenge
    {
        $challenge = AuthChallenge::find($challengeId);

        if (!$challenge || $challenge->consumed_at || $challenge->expires_at->isPast()) {
            return null;
        }

        // Verify code
        if (!Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');
            return null;
        }

        // Mark as consumed
        $challenge->update(['consumed_at' => now()]);

        return $challenge;
    }

    public function verifyMagicLink(string $token): ?AuthChallenge
    {
        $challenge = AuthChallenge::where('token', $token)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($challenge) {
            $challenge->update(['consumed_at' => now()]);
        }

        return $challenge;
    }
}
