<?php

namespace App\Services;

class UrlGuard
{
    /**
     * Assert the URL is safe to fetch: http/https only, no private/reserved IPs.
     * Throws \InvalidArgumentException on violation.
     */
    public static function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! $parts || ! isset($parts['scheme']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Only http/https URLs are allowed.');
        }

        if (! isset($parts['host'])) {
            throw new \InvalidArgumentException('URL must contain a host.');
        }

        $host = $parts['host'];

        // If host is an IP, validate it's public
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                throw new \InvalidArgumentException('Private or reserved IP addresses are not allowed.');
            }

            return;
        }

        // Resolve DNS and check resolved IP
        $resolved = gethostbyname($host);
        if ($resolved === $host) {
            // Could not resolve; allow and rely on HTTP client timeout.
            return;
        }
        if (filter_var($resolved, FILTER_VALIDATE_IP) && ! self::isPublicIp($resolved)) {
            throw new \InvalidArgumentException('Resolved IP is private or reserved; fetch blocked.');
        }
    }

    /** Determine if an IP is public (not private/reserved). */
    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
