<?php

namespace App\Services\Security;

final class PhishingLinkAnalyzer
{
    private const SHORTENERS = [
        'bit.ly',
        'tinyurl.com',
        't.co',
        'goo.gl',
        'ow.ly',
        'buff.ly',
        'is.gd',
        'cutt.ly',
        'tiny.cc',
        'rebrand.ly',
        'shorturl.at',
    ];

    private const SUSPICIOUS_WORDS = [
        'login',
        'signin',
        'verify',
        'verification',
        'secure',
        'account',
        'update',
        'confirm',
        'password',
        'wallet',
        'bank',
        'billing',
        'invoice',
        'payment',
        'recover',
        'unlock',
        'support',
        'security',
    ];

    public function analyze(string $input): array
    {
        $original = trim($input);

        if ($original === '') {
            throw new \InvalidArgumentException(
                'Enter a URL to analyze.'
            );
        }

        if (strlen($original) > 2048) {
            throw new \InvalidArgumentException(
                'The URL may not exceed 2048 characters.'
            );
        }

        $candidate = $original;

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parsed = parse_url($candidate);

        if (
            ! is_array($parsed) ||
            ! isset($parsed['scheme'], $parsed['host'])
        ) {
            throw new \InvalidArgumentException(
                'Enter a valid HTTP or HTTPS URL.'
            );
        }

        $scheme = strtolower((string) $parsed['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException(
                'Only HTTP and HTTPS URLs can be analyzed.'
            );
        }

        $host = strtolower(
            rtrim(trim((string) $parsed['host'], '[]'), '.')
        );

        if ($host === '') {
            throw new \InvalidArgumentException(
                'The URL hostname is missing.'
            );
        }

        $indicators = [];
        $score = 0;

        $add = function (
            string $code,
            string $severity,
            int $points,
            string $title,
            string $detail
        ) use (&$indicators, &$score): void {
            $score += $points;

            $indicators[] = [
                'code' => $code,
                'severity' => $severity,
                'points' => $points,
                'title' => $title,
                'detail' => $detail,
            ];
        };

        if ($scheme !== 'https') {
            $add(
                'no_https',
                'medium',
                12,
                'HTTPS is not used',
                'The URL uses unencrypted HTTP. This alone does not prove phishing.'
            );
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $add(
                'ip_hostname',
                'high',
                25,
                'IP address used as hostname',
                'The link uses an IP address instead of a conventional domain name.'
            );
        }

        if (
            isset($parsed['user']) ||
            isset($parsed['pass']) ||
            str_contains($original, '@')
        ) {
            $add(
                'userinfo_or_at',
                'high',
                25,
                'User-info or @ character detected',
                'The @ character can make the visible beginning of a URL misleading.'
            );
        }

        if (
            str_contains($host, 'xn--') ||
            preg_match('/[^\x20-\x7E]/u', $host)
        ) {
            $add(
                'idn_punycode',
                'high',
                22,
                'Internationalized or Punycode hostname',
                'Look-alike Unicode domains can be used for domain impersonation.'
            );
        }

        $labels = explode('.', $host);

        if (count($labels) >= 5) {
            $add(
                'many_subdomains',
                'medium',
                10,
                'Many hostname labels',
                'The hostname contains an unusually deep subdomain structure.'
            );
        }

        if (strlen($host) >= 45) {
            $add(
                'long_hostname',
                'medium',
                8,
                'Long hostname',
                'Long hostnames can make the registrable domain harder to notice.'
            );
        }

        if (strlen($candidate) >= 120) {
            $add(
                'long_url',
                'medium',
                8,
                'Long URL',
                'The URL is unusually long and may be harder to inspect visually.'
            );
        }

        $encodedCount = preg_match_all(
            '/%[0-9a-f]{2}/i',
            $original
        );

        if ($encodedCount !== false && $encodedCount >= 4) {
            $add(
                'heavy_encoding',
                'medium',
                10,
                'Heavy percent encoding',
                'Multiple encoded characters can obscure the visible URL structure.'
            );
        }

        if (
            isset($parsed['port']) &&
            ! in_array(
                (int) $parsed['port'],
                [80, 443],
                true
            )
        ) {
            $add(
                'nonstandard_port',
                'medium',
                10,
                'Non-standard web port',
                'The URL explicitly uses a port other than 80 or 443.'
            );
        }

        if ($this->isShortener($host)) {
            $add(
                'url_shortener',
                'medium',
                15,
                'URL shortening service',
                'Shortened links conceal the final destination until resolved.'
            );
        }

        $searchText = strtolower(
            $host.' '.
            ($parsed['path'] ?? '').' '.
            ($parsed['query'] ?? '')
        );

        $matchedWords = [];

        foreach (self::SUSPICIOUS_WORDS as $word) {
            if (str_contains($searchText, $word)) {
                $matchedWords[] = $word;
            }
        }

        $matchedWords = array_values(
            array_unique($matchedWords)
        );

        if (count($matchedWords) >= 2) {
            $add(
                'credential_language',
                'low',
                min(12, 4 + count($matchedWords) * 2),
                'Account or credential-related wording',
                'Terms detected: '.implode(', ', $matchedWords).
                '. Legitimate sites can also use these terms.'
            );
        }

        $hyphenCount = substr_count($host, '-');

        if ($hyphenCount >= 4) {
            $add(
                'many_hyphens',
                'low',
                6,
                'Many hostname hyphens',
                'The hostname contains several hyphens, which can make visual inspection harder.'
            );
        }

        $score = min(100, $score);

        $level = match (true) {
            $score >= 70 => 'critical',
            $score >= 45 => 'high',
            $score >= 20 => 'medium',
            default => 'low',
        };

        return [
            'input' => $original,
            'normalized_url' => $candidate,
            'hostname' => $host,
            'scheme' => $scheme,
            'port' => $parsed['port'] ?? null,
            'risk_score' => $score,
            'risk_level' => $level,
            'indicator_count' => count($indicators),
            'indicators' => $indicators,
            'network_request_performed' => false,
            'assessment' => $indicators === []
                ? 'No obvious static phishing indicators were detected. This does not prove that the link is safe.'
                : 'Static risk indicators were detected. Review the hostname and indicators before opening the link.',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function isShortener(string $host): bool
    {
        foreach (self::SHORTENERS as $shortener) {
            if (
                $host === $shortener ||
                str_ends_with($host, '.'.$shortener)
            ) {
                return true;
            }
        }

        return false;
    }
}
