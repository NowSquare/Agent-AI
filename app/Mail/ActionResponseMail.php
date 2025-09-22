<?php

namespace App\Mail;

use App\Models\Action;
use App\Models\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActionResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Action $action,
        public Thread $thread,
        public string $responseContent,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Create reply-to with thread ID for proper threading
        $agentMail = config('services.postmark.agent_mail', 'agent@inbound.postmarkapp.com');
        $replyToWithThread = str_replace('@', "+{$this->thread->id}@", $agentMail);

        return new Envelope(
            subject: "Re: {$this->thread->subject}",
            replyTo: [$replyToWithThread],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.action-response',
            with: [
                'action' => $this->action,
                'thread' => $this->thread,
                'responseContent' => $this->responseContent,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
