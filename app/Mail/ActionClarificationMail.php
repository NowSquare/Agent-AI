<?php

namespace App\Mail;

use App\Models\Action;
use App\Models\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ActionClarificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Action $action,
        public Thread $thread,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->buildSubject(),
            replyTo: $this->buildReplyToAddress(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Prefer HTML view with text fallback so clients render properly
        return new Content(
            html: 'emails.clarification',
            text: 'emails.clarification-text',
            with: [
                'action' => $this->action,
                'thread' => $this->thread,
                'confirmUrl' => $this->getConfirmUrl(),
                'cancelUrl' => $this->getCancelUrl(),
                'summary' => $this->getActionSummary(),
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

    /**
     * Build reply-to address with thread ID for continuity.
     */
    private function buildReplyToAddress(): string
    {
        $agentMail = config('services.postmark.agent_mail', 'agent@inbound.postmarkapp.com');

        return str_replace('@', "+{$this->thread->id}@", $agentMail);
    }

    /**
     * Get the confirmation URL.
     */
    private function getConfirmUrl(): string
    {
        return URL::signedRoute('action.confirm.show', [
            'action' => $this->action->id,
        ], now()->addHours(72)); // 72 hours expiry
    }

    /**
     * Get the cancel URL.
     */
    private function getCancelUrl(): string
    {
        return URL::signedRoute('action.confirm.cancel', [
            'action' => $this->action->id,
        ], now()->addHours(72)); // 72 hours expiry
    }

    /**
     * Get a human-readable summary of the action.
     */
    private function getActionSummary(): string
    {
        $type = $this->action->type;
        $params = $this->action->payload_json;

        return match ($type) {
            'info_request' => "Request for information: \"{$params['question']}\"",
            'approve' => 'Approval request',
            'reject' => 'Rejection request',
            'revise' => 'Request for changes',
            'stop' => 'Request to end conversation',
            default => "Action: {$type}",
        };
    }

    private function buildSubject(): string
    {
        $type = $this->action->type;
        return match ($type) {
            'info_request' => 'We need your input to proceed',
            'approve' => 'Please confirm approval',
            'reject' => 'Please confirm rejection',
            'revise' => 'Please confirm requested changes',
            'stop' => 'Confirm: end conversation',
            default => 'Please confirm',
        };
    }
}
