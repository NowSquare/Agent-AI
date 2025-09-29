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

class ActionOptionsMail extends Mailable
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
            subject: $this->subjectText(),
            replyTo: $this->buildReplyToAddress(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.options',
            text: 'emails.options-text',
            with: [
                'action' => $this->action,
                'thread' => $this->thread,
                'options' => $this->getOptions(),
                'replyUrl' => $this->getReplyUrl(),
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
     * Get the available options for this action.
     */
    private function getOptions(): array
    {
        $originalQuestion = $this->action->payload_json['question'] ?? '';

        // Generate contextual options based on the original question
        if (str_contains(strtolower($originalQuestion), 'recipe')) {
            return [
                [
                    'key' => 'italian_pasta',
                    'label' => 'Italian Pasta Recipe',
                    'url' => $this->getOptionUrl('italian_pasta'),
                ],
                [
                    'key' => 'greek_salad',
                    'label' => 'Greek Salad Recipe',
                    'url' => $this->getOptionUrl('greek_salad'),
                ],
                [
                    'key' => 'something_else',
                    'label' => 'Something Else',
                    'url' => $this->getOptionUrl('something_else'),
                ],
            ];
        }

        // Default options for general questions
        return [
            [
                'key' => 'general_help',
                'label' => 'General Help/Information',
                'url' => $this->getOptionUrl('general_help'),
            ],
            [
                'key' => 'specific_question',
                'label' => 'Ask a Specific Question',
                'url' => $this->getOptionUrl('specific_question'),
            ],
            [
                'key' => 'clarify_request',
                'label' => 'Need to Clarify My Request',
                'url' => $this->getOptionUrl('clarify_request'),
            ],
        ];
    }

    /**
     * Get URL for a specific option.
     */
    private function getOptionUrl(string $optionKey): string
    {
        return URL::signedRoute('action.options.choose', [
            'action' => $this->action->id,
            'key' => $optionKey,
        ], now()->addHours(72)); // 72 hours expiry
    }

    /**
     * Get URL for manual reply clarification.
     */
    private function getReplyUrl(): string
    {
        return $this->buildReplyToAddress();
    }

    /**
     * Build a contextual subject for options email.
     */
    private function subjectText(): string
    {
        $originalQuestion = $this->action->payload_json['question'] ?? '';
        if ($originalQuestion !== '') {
            return 'We need your input: choose an option';
        }
        return 'We need your input (options inside)';
    }
}
