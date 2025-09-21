<?php

namespace App\Services;

use App\Models\EmailMessage;
use App\Models\Thread;

class ThreadResolver
{
    /**
     * Resolve or create a thread for an incoming email message.
     */
    public function resolve(array $emailData, string $accountId): Thread
    {
        // Try to find existing thread by threading headers
        $thread = $this->findThreadByHeaders($emailData, $accountId);

        if ($thread) {
            return $thread;
        }

        // Create new thread
        return $this->createThread($emailData, $accountId);
    }

    /**
     * Find existing thread using RFC 5322 threading headers.
     */
    private function findThreadByHeaders(array $emailData, string $accountId): ?Thread
    {
        $messageId = $emailData['message_id'] ?? null;
        $inReplyTo = $emailData['in_reply_to'] ?? null;
        $references = $emailData['references'] ?? null;

        // 1. If In-Reply-To exists, find the parent message's thread
        if ($inReplyTo) {
            $parentMessage = EmailMessage::where('message_id', $inReplyTo)->first();
            if ($parentMessage) {
                return $parentMessage->thread;
            }
        }

        // 2. Check References header for any known message IDs
        if ($references) {
            $referenceIds = $this->parseReferences($references);
            $existingMessage = EmailMessage::whereIn('message_id', $referenceIds)->first();
            if ($existingMessage) {
                return $existingMessage->thread;
            }
        }

        // 3. Check if this message ID is already in our system (shouldn't happen but safety check)
        if ($messageId) {
            $existingMessage = EmailMessage::where('message_id', $messageId)->first();
            if ($existingMessage) {
                return $existingMessage->thread;
            }
        }

        // 4. Try subject-based threading as fallback
        $subject = $emailData['subject'] ?? '';
        if ($this->isReplySubject($subject)) {
            $baseSubject = $this->normalizeSubject($subject);
            $existingThread = Thread::where('account_id', $accountId)
                ->where('subject', $baseSubject)
                ->first();
            if ($existingThread) {
                return $existingThread;
            }
        }

        return null;
    }

    /**
     * Create a new thread for the email.
     */
    private function createThread(array $emailData, string $accountId): Thread
    {
        $subject = $emailData['subject'] ?? '';
        $normalizedSubject = $this->normalizeSubject($subject);

        return Thread::create([
            'account_id' => $accountId,
            'subject' => $normalizedSubject,
            'context_json' => [
                'message_count' => 0,
                'participant_count' => 0,
                'last_activity' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Parse References header into array of message IDs.
     */
    private function parseReferences(?string $references): array
    {
        if (!$references) {
            return [];
        }

        // References header contains space-separated message IDs in angle brackets
        preg_match_all('/<([^>]+)>/', $references, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Check if subject looks like a reply (Re:, Fwd:, etc.).
     */
    private function isReplySubject(string $subject): bool
    {
        return preg_match('/^(re|fwd|fw):\s*/i', $subject);
    }

    /**
     * Normalize subject for threading (remove Re:/Fwd: prefixes).
     */
    private function normalizeSubject(string $subject): string
    {
        return preg_replace('/^(re|fwd|fw):\s*/i', '', trim($subject));
    }
}
