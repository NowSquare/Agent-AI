<?php

namespace App\Mail;

use App\Models\AuthChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AuthChallengeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuthChallenge $challenge,
        public string $code
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your login code for Agent AI',
        );
    }

    public function content(): Content
    {
        $magicLink = URL::signedRoute('login.magic', ['token' => $this->challenge->token], 15 * 60); // 15 minutes

        return new Content(
            view: 'emails.auth-challenge',
            with: [
                'code' => $this->code,
                'magicLink' => $magicLink,
                'expiresAt' => $this->challenge->expires_at,
            ],
        );
    }
}
