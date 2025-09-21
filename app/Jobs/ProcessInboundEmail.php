<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\EmailInboundPayload;
use App\Models\EmailMessage;
use App\Services\ReplyCleaner;
use App\Services\ThreadResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30; // 30 seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $payloadId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReplyCleaner $replyCleaner, ThreadResolver $threadResolver): void
    {
        Log::info('Processing inbound email', ['payload_id' => $this->payloadId]);

        // Retrieve and decrypt payload
        $payloadRecord = EmailInboundPayload::findOrFail($this->payloadId);
        $payload = json_decode(Crypt::decryptString($payloadRecord->ciphertext), true);

        if (!$payload) {
            Log::error('Failed to decrypt or decode payload', ['payload_id' => $this->payloadId]);
            return;
        }

        // Parse Postmark payload
        $emailData = $this->parsePostmarkPayload($payload);

        // Get or create account (for now, use default account)
        $account = Account::firstOrCreate([
            'name' => 'Default Account',
        ]);

        // Clean reply text
        $cleanReply = $replyCleaner->clean($emailData['text_body'] ?? '', $emailData['html_body'] ?? '');

        // Resolve thread
        $thread = $threadResolver->resolveOrCreate(
            account: $account,
            subject: $emailData['subject'],
            messageId: $emailData['message_id'],
            inReplyTo: $emailData['in_reply_to'],
            references: $emailData['references']
        );

        // Create email message
        $emailMessage = EmailMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'inbound',
            'message_id' => $emailData['message_id'],
            'in_reply_to' => $emailData['in_reply_to'],
            'references' => $emailData['references'],
            'from_email' => $emailData['from_email'],
            'from_name' => $emailData['from_name'],
            'to_json' => $emailData['to'],
            'cc_json' => $emailData['cc'] ?? [],
            'bcc_json' => $emailData['bcc'] ?? [],
            'subject' => $emailData['subject'],
            'headers_json' => $emailData['headers'] ?? [],
            'text_body' => $emailData['text_body'],
            'html_body' => $emailData['html_body'],
            'x_thread_id' => $emailData['x_thread_id'] ?? null,
            'raw_size_bytes' => $emailData['raw_size_bytes'] ?? null,
        ]);

        // Register attachments (if any)
        if (!empty($emailData['attachments'])) {
            $this->registerAttachments($emailMessage, $emailData['attachments']);
        }

        Log::info('Email processed successfully', [
            'payload_id' => $this->payloadId,
            'thread_id' => $thread->id,
            'message_id' => $emailMessage->id,
            'clean_reply_length' => strlen($cleanReply),
        ]);

        // TODO: Next steps (Phase 2)
        // 1. LLM interpretation of clean reply
        // 2. Action creation based on interpretation
        // 3. Memory storage
        // 4. Outbound email responses
    }

    /**
     * Parse Postmark inbound webhook payload.
     */
    private function parsePostmarkPayload(array $payload): array
    {
        return [
            'message_id' => $payload['MessageID'] ?? null,
            'in_reply_to' => $payload['InReplyTo'] ?? null,
            'references' => $payload['References'] ?? null,
            'from_email' => $this->extractEmail($payload['From'] ?? ''),
            'from_name' => $this->extractName($payload['From'] ?? ''),
            'to' => $this->parseRecipients($payload['To'] ?? ''),
            'cc' => $this->parseRecipients($payload['Cc'] ?? ''),
            'bcc' => $this->parseRecipients($payload['Bcc'] ?? ''),
            'subject' => $payload['Subject'] ?? '',
            'text_body' => $payload['TextBody'] ?? '',
            'html_body' => $payload['HtmlBody'] ?? '',
            'headers' => $payload['Headers'] ?? [],
            'x_thread_id' => $this->findHeader($payload['Headers'] ?? [], 'X-Thread-ID'),
            'attachments' => $payload['Attachments'] ?? [],
            'raw_size_bytes' => strlen($payload['TextBody'] ?? '') + strlen($payload['HtmlBody'] ?? ''),
        ];
    }

    /**
     * Extract email from "Name <email>" format.
     */
    private function extractEmail(string $address): string
    {
        if (preg_match('/<([^>]+)>/', $address, $matches)) {
            return strtolower(trim($matches[1]));
        }
        return strtolower(trim($address));
    }

    /**
     * Extract name from "Name <email>" format.
     */
    private function extractName(string $address): string
    {
        if (preg_match('/^([^<]+)</', $address, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Parse recipients string into array format.
     */
    private function parseRecipients(string $recipients): array
    {
        if (empty($recipients)) {
            return [];
        }

        $result = [];
        $parts = explode(',', $recipients);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            $result[] = [
                'email' => $this->extractEmail($part),
                'name' => $this->extractName($part),
            ];
        }

        return $result;
    }

    /**
     * Find header value by name.
     */
    private function findHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (strtolower($header['Name'] ?? '') === strtolower($name)) {
                return $header['Value'] ?? null;
            }
        }
        return null;
    }

    /**
     * Register attachments for the email message.
     */
    private function registerAttachments(EmailMessage $emailMessage, array $attachments): void
    {
        // TODO: Implement attachment processing
        // For now, just log them
        Log::info('Attachments found', [
            'message_id' => $emailMessage->id,
            'count' => count($attachments),
        ]);
    }
}
