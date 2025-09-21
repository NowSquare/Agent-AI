<?php

namespace App\Services;

class ReplyCleaner
{
    /**
     * Clean email reply text by removing quoted content.
     */
    public function clean(string $textBody, string $htmlBody = ''): string
    {
        // Prefer text body for cleaning, fallback to HTML stripped version
        $text = !empty($textBody) ? $textBody : $this->stripHtml($htmlBody);

        if (empty($text)) {
            return '';
        }

        // Remove signatures first
        $text = $this->removeSignatures($text);

        // Remove quoted content
        $text = $this->removeQuotedContent($text);

        // Clean up whitespace
        $text = $this->cleanWhitespace($text);

        return trim($text);
    }

    /**
     * Strip HTML tags and convert to plain text.
     */
    private function stripHtml(string $html): string
    {
        // Basic HTML stripping - could be enhanced with libraries like Html2Text
        $text = strip_tags($html);

        // Convert common HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        return $text;
    }

    /**
     * Remove email signatures.
     */
    private function removeSignatures(string $text): string
    {
        $lines = explode("\n", $text);
        $result = [];
        $inSignature = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Common signature markers
            if (preg_match('/^--\s*$/', $line) ||
                preg_match('/^--\s*$/', $line) ||
                str_contains(strtolower($line), 'sent from') ||
                str_contains(strtolower($line), 'verzonden vanaf') ||
                preg_match('/^best regards?[,]?$/i', $line) ||
                preg_match('/^regards?[,]?$/i', $line) ||
                preg_match('/^cheers[,]?$/i', $line) ||
                preg_match('/^thanks[,]?$/i', $line) ||
                preg_match('/^groeten[,]?$/i', $line) ||
                preg_match('/^met vriendelijke groet[,]?$/i', $line)) {
                $inSignature = true;
                break;
            }

            if (!$inSignature) {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Remove quoted content from replies.
     */
    private function removeQuotedContent(string $text): string
    {
        $lines = explode("\n", $text);
        $result = [];
        $inQuote = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Check for quote markers
            if ($this->isQuoteMarker($trimmed)) {
                $inQuote = true;
                continue;
            }

            // If we're in a quote block, skip the line
            if ($inQuote) {
                continue;
            }

            // If line starts with quote character, skip it
            if (str_starts_with($trimmed, '>') ||
                str_starts_with($trimmed, '|') ||
                preg_match('/^\s*[>\|]/', $trimmed)) {
                continue;
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    /**
     * Check if a line is a quote marker.
     */
    private function isQuoteMarker(string $line): bool
    {
        // Common quote markers in various languages
        $patterns = [
            '/^On\s+.*\s+wrote:$/i',           // "On [date] [person] wrote:"
            '/^Op\s+.*\s+schreef.*:$/i',       // Dutch: "Op [date] [person] schreef:"
            '/^Le\s+.*\s+a\s+écrit\s*:/i',     // French: "Le [date] [person] a écrit :"
            '/^Am\s+.*\s+schrieb.*:$/i',       // German: "Am [date] [person] schrieb:"
            '/^El\s+.*\s+escribió:$/i',        // Spanish: "El [date] [person] escribió:"
            '/^Il\s+.*\s+ha\s+scritto:$/i',    // Italian: "Il [date] [person] ha scritto:"
            '/^Forwarded\s+message:/i',        // Forward markers
            '/^Doorstuurbbericht:/i',          // Dutch forward
            '/^---+\s*Forwarded\s+message\s*---+$/i',
            '/^Begin\s+forwarded\s+message:/i',
            '/^Original\s+message:/i',
            '/^Oorspronkelijk\s+bericht:/i',   // Dutch original
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean up whitespace and formatting.
     */
    private function cleanWhitespace(string $text): string
    {
        // Remove excessive blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Remove trailing whitespace from each line
        $lines = explode("\n", $text);
        $lines = array_map('rtrim', $lines);

        // Remove leading/trailing empty lines
        $lines = array_filter($lines, fn($line) => !empty(trim($line)) || str_contains($line, ' '));

        return implode("\n", $lines);
    }
}
