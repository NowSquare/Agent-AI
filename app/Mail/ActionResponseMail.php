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
        // Build from / reply-to using AGENT_MAIL
        $agentMail = (string) config('mail.agent_mail', env('AGENT_MAIL'));
        // from address is AGENT_MAIL
        $fromAddress = $agentMail;
        // reply-to is AGENT_MAIL with +<thread_id>
        if (str_contains($agentMail, '@')) {
            [$local, $domain] = explode('@', $agentMail, 2);
            $replyToWithThread = $local.'+'.$this->thread->id.'@'.$domain;
        } else {
            $replyToWithThread = $agentMail;
        }

        return new Envelope(
            subject: "Re: {$this->thread->subject}",
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, config('mail.from.name')),
            replyTo: [new \Illuminate\Mail\Mailables\Address($replyToWithThread)],
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
                // Outcome-driven flags/fields for the view
                'success' => $this->action->status === 'completed',
                'detailsUrl' => null,
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
