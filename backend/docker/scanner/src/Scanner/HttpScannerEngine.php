<?php

namespace App\Services\Scanner;

use RuntimeException;

class HttpScannerEngine
{
    private readonly DnsResolver $dnsResolver;
    private readonly DnsIntelligenceEngine $dnsIntelligenceEngine;
    private readonly TlsIntelligenceEngine $tlsIntelligenceEngine;

    public function __construct(
        private readonly HttpTransport $httpTransport,
        ?DnsResolver $dnsResolver = null,
        ?DnsIntelligenceEngine $dnsIntelligenceEngine = null,
        ?TlsIntelligenceEngine $tlsIntelligenceEngine = null
    ) {
        $this->dnsResolver =
            $dnsResolver ?? new SystemDnsResolver();

        $this->dnsIntelligenceEngine =
            $dnsIntelligenceEngine ?? new DnsIntelligenceEngine();

        $this->tlsIntelligenceEngine =
            $tlsIntelligenceEngine ?? new TlsIntelligenceEngine();
    }

    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const REQUEST_TIMEOUT_SECONDS = 10;
    private const MAX_RESPONSE_BYTES = 2_097_152; // 2 MiB

public function scan(array $request): array
    {
        $request = $this->validateScannerRequest($request);
        $target = $request['target'];

        $canonical = $this->canonicalizeTargetUrl($target);

        $url = $canonical['url'];
        $scheme = $canonical['scheme'];
        $host = $canonical['host'];
        $port = $canonical['port'];
        $targetId = $target['id'];

        $resolved = $this->resolveAndValidateHost($host);

        /*
         * DNS Intelligence V1 runs only after the authorized target
         * hostname has passed the scanner's destination safety gate.
         *
         * It is passive record intelligence for the authorized hostname;
         * no subdomain enumeration is performed here.
         */
        $dnsIntelligence = $this->dnsIntelligenceEngine
            ->analyze($host);

        $requestResult = $this->performPinnedRequest(
            $url,
            $host,
            $port,
            $resolved
        );

        if (! $requestResult['ok']) {
            throw new RuntimeException(
                'HTTP assessment request failed: ' .
                $requestResult['error']['type']
            );
        }

        $durationMs = $requestResult['duration_ms'];

        $rawHeaders = $this->normalizeRawHeaders(
            $requestResult['headers']
        );

        $headers = $this->flattenHeaders(
            $rawHeaders
        );

        $findings = [];

        $this->checkSecurityHeaders(
            $targetId,
            $url,
            $scheme,
            $requestResult['status'],
            $headers,
            $findings
        );

        $this->checkServerDisclosure(
            $targetId,
            $url,
            $headers,
            $findings
        );

        $this->checkCookies(
            $targetId,
            $url,
            $scheme,
            $rawHeaders,
            $findings
        );

        $this->checkCors(
            $targetId,
            $url,
            $headers,
            $findings
        );

        $this->probeCorsOriginReflection(
            $targetId,
            $url,
            $host,
            $port,
            $resolved,
            $findings
        );

        $this->checkHttpMethods(
            $targetId,
            $url,
            $headers,
            $findings
        );

        $redirect = $this->header($headers, 'location');
        $redirectChain = [];

        if ($redirect !== null) {
            $this->recordRedirectFinding(
                $targetId,
                $url,
                $redirect,
                $findings
            );

            $redirectChain = $this->analyzeRedirectChain(
                $url,
                $host,
                $requestResult['status'],
                $headers
            );
        }

        $tls = $scheme === 'https'
            ? $this->inspectTls($host, $port, $resolved)
            : [
                'enabled' => false,
                'certificate' => null,
            ];

        $this->checkTlsCertificateFindings(
            $targetId,
            $url,
            $tls,
            $findings
        );

        return [
            'engine_version' => 'http-assessment-v3',

            'scanner_policy' => [
                'authorization_scope' => 'single_target',
                'dns_pinning' => true,
                'tls_verification' => true,
                'automatic_redirects' => false,
                'same_host_redirects_only' => true,
                'redirect_max_hops' => 5,
                'connect_timeout_seconds' => self::CONNECT_TIMEOUT_SECONDS,
                'request_timeout_seconds' => self::REQUEST_TIMEOUT_SECONDS,
                'max_response_bytes' => self::MAX_RESPONSE_BYTES,
            ],

            'finding_identity' => [
                'version' => 1,
                'algorithm' => 'sha256',
                'components' => [
                    'target_id',
                    'type',
                    'title',
                ],
            ],

            'http_status' => $requestResult['status'],
            'successful' => $requestResult['successful'],
            'duration_ms' => $durationMs,
            'content_type' => $this->header($headers, 'content-type'),
            'server' => $this->header($headers, 'server'),
            'powered_by' => $this->header($headers, 'x-powered-by'),
            'redirect' => $redirect,
            'redirect_chain' => $redirectChain,
            'redirect_hops' => count($redirectChain),
            'resolved_ips' => $resolved,
            'dns_intelligence' => $dnsIntelligence,
            'tls' => $tls,
            'headers' => $headers,
            'finding_count' => count($findings),
            'checked_at' => $this->utcNowIso8601(),

            /*
             * Internal Scanner Result V1 payload.
             * run() persists this collection and removes it before returning
             * execution metadata to RunAssessment.
             */
            'findings' => $findings,
        ];
    }

    private function validateScannerRequest(array $request): array
    {
        if (($request['contract_version'] ?? null) !== 1) {
            throw new RuntimeException(
                'Unsupported scanner request contract version.'
            );
        }

        $assessmentId = $request['assessment_id'] ?? null;
        $target = $request['target'] ?? null;

        if (
            ! is_string($assessmentId) ||
            trim($assessmentId) === '' ||
            ! is_array($target)
        ) {
            throw new RuntimeException(
                'Scanner request is malformed.'
            );
        }

        foreach (
            ['id', 'url', 'hostname', 'scheme', 'port']
            as $field
        ) {
            if (! array_key_exists($field, $target)) {
                throw new RuntimeException(
                    "Scanner request target field is missing: {$field}."
                );
            }
        }

        foreach (['id', 'url', 'hostname', 'scheme'] as $field) {
            if (
                ! is_string($target[$field]) ||
                trim($target[$field]) === ''
            ) {
                throw new RuntimeException(
                    "Scanner request target field is invalid: {$field}."
                );
            }
        }

        if (
            ! is_int($target['port']) ||
            $target['port'] < 1 ||
            $target['port'] > 65535
        ) {
            throw new RuntimeException(
                'Scanner request target port is invalid.'
            );
        }

        return [
            'contract_version' => 1,
            'assessment_id' => trim($assessmentId),
            'target' => [
                'id' => trim($target['id']),
                'url' => trim($target['url']),
                'hostname' => trim($target['hostname']),
                'scheme' => strtolower(trim($target['scheme'])),
                'port' => $target['port'],
            ],
        ];
    }

private function canonicalizeTargetUrl(array $target): array
    {
        $rawUrl = trim((string) $target['url']);

        if ($rawUrl === '') {
            throw new RuntimeException('Target URL is empty.');
        }

        /*
         * Reject URL parser ambiguity before parse_url().
         * Backslashes are especially dangerous because different URL
         * implementations may interpret them differently.
         */
        if (
            str_contains($rawUrl, '\\') ||
            preg_match('/[\x00-\x20\x7f]/', $rawUrl)
        ) {
            throw new RuntimeException(
                'Target URL contains unsafe or ambiguous characters.'
            );
        }

        $parsed = parse_url($rawUrl);

        if (
            ! is_array($parsed) ||
            ! isset($parsed['scheme'], $parsed['host'])
        ) {
            throw new RuntimeException('Target URL is invalid.');
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            throw new RuntimeException(
                'Target URLs containing user information are not allowed.'
            );
        }

        if (isset($parsed['fragment'])) {
            throw new RuntimeException(
                'Target URL fragments are not allowed.'
            );
        }

        $scheme = strtolower(trim((string) $parsed['scheme']));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(
                'Only HTTP and HTTPS targets are allowed.'
            );
        }

        $host = $this->normalizeHost(
            (string) $parsed['host']
        );

        if ($host === '') {
            throw new RuntimeException(
                'Target hostname is empty.'
            );
        }

        $port = isset($parsed['port'])
            ? (int) $parsed['port']
            : ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException(
                'Target port is outside the valid TCP port range.'
            );
        }

        $storedScheme = strtolower(
            trim((string) ($target['scheme'] ?? ''))
        );

        if (
            $storedScheme !== '' &&
            $storedScheme !== $scheme
        ) {
            throw new RuntimeException(
                'Target URL scheme does not match the authorized target scheme.'
            );
        }

        $storedHost = $this->normalizeHost(
            (string) ($target['hostname'] ?? '')
        );

        if (
            $storedHost === '' ||
            ! $this->hostsEquivalent($storedHost, $host)
        ) {
            throw new RuntimeException(
                'Target URL hostname does not match the authorized target hostname.'
            );
        }

        $storedPort = (int) ($target['port'] ?? 0);

        if (
            $storedPort > 0 &&
            $storedPort !== $port
        ) {
            throw new RuntimeException(
                'Target URL port does not match the authorized target port.'
            );
        }

        $path = isset($parsed['path'])
            ? (string) $parsed['path']
            : '/';

        if ($path === '') {
            $path = '/';
        }

        if (! str_starts_with($path, '/')) {
            throw new RuntimeException(
                'Target URL path is invalid.'
            );
        }

        $authorityHost = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        )
            ? "[{$host}]"
            : $host;

        $defaultPort = $scheme === 'https'
            ? 443
            : 80;

        $authority = $authorityHost;

        if ($port !== $defaultPort) {
            $authority .= ':' . $port;
        }

        $canonicalUrl =
            $scheme . '://' . $authority . $path;

        if (
            isset($parsed['query']) &&
            $parsed['query'] !== ''
        ) {
            $canonicalUrl .= '?' . $parsed['query'];
        }

        return [
            'url' => $canonicalUrl,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
        ];
    }

    private function normalizeHost(string $hostname): string
    {
        $hostname = strtolower(
            trim($hostname)
        );

        $hostname = trim($hostname, '[]');

        /*
         * A trailing dot represents the DNS root and is semantically
         * equivalent for normal FQDN authorization comparison.
         */
        if (
            ! filter_var(
                $hostname,
                FILTER_VALIDATE_IP
            )
        ) {
            $hostname = rtrim(
                $hostname,
                '.'
            );
        }

        return $hostname;
    }

    private function hostsEquivalent(
        string $left,
        string $right
    ): bool {
        $left = $this->normalizeHost($left);
        $right = $this->normalizeHost($right);

        $leftIsIp = filter_var(
            $left,
            FILTER_VALIDATE_IP
        ) !== false;

        $rightIsIp = filter_var(
            $right,
            FILTER_VALIDATE_IP
        ) !== false;

        if ($leftIsIp || $rightIsIp) {
            if (! $leftIsIp || ! $rightIsIp) {
                return false;
            }

            $leftPacked = @inet_pton($left);
            $rightPacked = @inet_pton($right);

            return
                $leftPacked !== false &&
                $rightPacked !== false &&
                hash_equals(
                    $leftPacked,
                    $rightPacked
                );
        }

        return $left === $right;
    }

    private function resolveAndValidateHost(string $hostname): array
    {
        if ($hostname === '') {
            throw new RuntimeException('Target hostname is empty.');
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($hostname);

            return [$hostname];
        }

        if (! preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $hostname)) {
            throw new RuntimeException('Target hostname is invalid.');
        }

        $ips = $this->dnsResolver->resolve(
            $hostname
        );

        if ($ips === []) {
            throw new RuntimeException(
                'Target hostname could not be resolved.'
            );
        }

        foreach ($ips as $ip) {
            if (! is_string($ip) || $ip === '') {
                throw new RuntimeException(
                    'Target hostname returned an invalid DNS address.'
                );
            }

            $this->assertPublicIp($ip);
        }

        $ips = array_values(array_unique($ips));

        if ($ips === []) {
            throw new RuntimeException('Target did not resolve to a usable public IP address.');
        }

        return $ips;
    }

    private function assertPublicIp(string $ip): void
    {
        if (
            ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )
        ) {
            throw new RuntimeException(
                'Private, reserved, loopback, link-local, or otherwise unsafe IP targets are not allowed.'
            );
        }
    }

private function checkSecurityHeaders(
        string $targetId,
        string $url,
        string $scheme,
        int $status,
        array $headers,
        array &$findings
    ): void {
        $definitions = [
            'content-security-policy' => [
                'title' => 'Content-Security-Policy header missing',
                'severity' => 'medium',
                'remediation' => 'Define an appropriate Content-Security-Policy for the application.',
            ],
            'x-content-type-options' => [
                'title' => 'X-Content-Type-Options header missing',
                'severity' => 'low',
                'remediation' => 'Set X-Content-Type-Options: nosniff.',
            ],
            'referrer-policy' => [
                'title' => 'Referrer-Policy header missing',
                'severity' => 'low',
                'remediation' => 'Configure an appropriate Referrer-Policy.',
            ],
        ];

        foreach ($definitions as $header => $definition) {
            if ($this->header($headers, $header) === null) {
                $this->createFinding(
                    $targetId,
                    'security_header',
                    $definition['title'],
                    "The HTTP {$status} response did not include the {$header} security header.",
                    $definition['severity'],
                    'high',
                    [
                        'url' => $url,
                        'status' => $status,
                        'header' => $header,
                        'observed_at' => $this->utcNowIso8601(),
                    ],
                    $definition['remediation'],
                    $findings
                );
            }
        }

        /*
         * HSTS is meaningful only when delivered over HTTPS.
         * Do not report its absence on a plain HTTP response.
         */
        if ($scheme === 'https') {
            $hsts = $this->header(
                $headers,
                'strict-transport-security'
            );

            if ($hsts === null) {
                $this->createFinding(
                    $targetId,
                    'security_header',
                    'Strict-Transport-Security header missing',
                    'The HTTPS response did not include the Strict-Transport-Security header.',
                    'medium',
                    'high',
                    [
                        'url' => $url,
                        'status' => $status,
                        'header' => 'strict-transport-security',
                    ],
                    'Configure HSTS after confirming HTTPS is correctly deployed.',
                    $findings
                );
            } else {
                $maxAge = null;

                if (
                    preg_match(
                        '/(?:^|;)\s*max-age\s*=\s*(\d+)/i',
                        $hsts,
                        $match
                    )
                ) {
                    $maxAge = (int) $match[1];
                }

                if ($maxAge === null) {
                    $this->createFinding(
                        $targetId,
                        'security_header',
                        'Strict-Transport-Security value appears invalid',
                        'The HSTS header was present, but no valid max-age directive was observed.',
                        'medium',
                        'medium',
                        [
                            'url' => $url,
                            'header' => 'strict-transport-security',
                            'value' => $hsts,
                        ],
                        'Configure HSTS with a valid max-age directive.',
                        $findings
                    );
                } elseif ($maxAge === 0) {
                    $this->createFinding(
                        $targetId,
                        'security_header',
                        'Strict-Transport-Security is effectively disabled',
                        'The HSTS max-age directive is set to zero.',
                        'medium',
                        'high',
                        [
                            'url' => $url,
                            'header' => 'strict-transport-security',
                            'value' => $hsts,
                            'max_age' => 0,
                        ],
                        'Use a positive HSTS max-age after confirming HTTPS is correctly deployed.',
                        $findings
                    );
                }
            }
        }

        $csp = $this->header(
            $headers,
            'content-security-policy'
        );

        if (
            $csp !== null &&
            trim($csp) === ''
        ) {
            $this->createFinding(
                $targetId,
                'security_header',
                'Content-Security-Policy header is empty',
                'The Content-Security-Policy header is present but contains no policy.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'header' => 'content-security-policy',
                ],
                'Define an explicit Content-Security-Policy appropriate for the application.',
                $findings
            );
        }

        if (
            $csp !== null &&
            trim($csp) !== ''
        ) {
            $this->checkContentSecurityPolicy(
                $targetId,
                $url,
                $csp,
                $findings
            );
        }

        $xcto = $this->header($headers, 'x-content-type-options');

        if ($xcto !== null && strtolower(trim($xcto)) !== 'nosniff') {
            $this->createFinding(
                $targetId,
                'security_header',
                'X-Content-Type-Options has unexpected value',
                'The X-Content-Type-Options header was present but was not set to nosniff.',
                'low',
                'high',
                [
                    'url' => $url,
                    'header' => 'x-content-type-options',
                    'value' => $xcto,
                ],
                'Set X-Content-Type-Options: nosniff.',
                $findings
            );
        }

        $referrerPolicy = $this->header(
            $headers,
            'referrer-policy'
        );

        if ($referrerPolicy !== null) {
            $this->checkReferrerPolicy(
                $targetId,
                $url,
                $referrerPolicy,
                $findings
            );
        }

        $this->checkFrameProtectionPolicy(
            $targetId,
            $url,
            $this->header($headers, 'x-frame-options'),
            $csp,
            $findings
        );
    }

    private function parseContentSecurityPolicyDirectives(
        string $policy
    ): array {
        $directives = [];

        foreach (explode(';', $policy) as $rawDirective) {
            $rawDirective = trim($rawDirective);

            if ($rawDirective === '') {
                continue;
            }

            $parts = preg_split(
                '/\s+/',
                $rawDirective
            );

            if (
                ! is_array($parts) ||
                $parts === []
            ) {
                continue;
            }

            $name = strtolower(
                (string) array_shift($parts)
            );

            if ($name === '') {
                continue;
            }

            /*
             * Preserve the existing bounded CSP V1 behavior:
             * first occurrence wins.
             */
            if (array_key_exists($name, $directives)) {
                continue;
            }

            $directives[$name] = array_values(
                array_map(
                    static fn ($value): string =>
                        strtolower((string) $value),
                    $parts
                )
            );
        }

        return $directives;
    }

    private function checkReferrerPolicy(
        string $targetId,
        string $url,
        string $value,
        array &$findings
    ): void {
        $recognized = [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url',
        ];

        $effective = null;

        foreach (explode(',', strtolower($value)) as $candidate) {
            $candidate = trim($candidate);

            if (in_array($candidate, $recognized, true)) {
                /*
                 * Referrer-Policy supports fallback lists.
                 * The last recognized token is the effective policy.
                 */
                $effective = $candidate;
            }
        }

        if ($effective === null) {
            $this->createFinding(
                $targetId,
                'security_header',
                'Referrer-Policy has no recognized policy',
                'The Referrer-Policy header was present but contained no recognized policy token.',
                'low',
                'high',
                [
                    'url' => $url,
                    'header' => 'referrer-policy',
                    'value' => $value,
                ],
                'Configure a recognized Referrer-Policy appropriate for the application.',
                $findings
            );

            return;
        }

        if ($effective !== 'unsafe-url') {
            return;
        }

        $this->createFinding(
            $targetId,
            'security_header',
            'Referrer-Policy permits full URL referrer disclosure',
            'The effective Referrer-Policy is unsafe-url, which can send the full referrer URL to cross-origin destinations.',
            'low',
            'high',
            [
                'url' => $url,
                'header' => 'referrer-policy',
                'value' => $value,
                'effective_policy' => $effective,
            ],
            'Use a stricter Referrer-Policy such as strict-origin-when-cross-origin unless the application explicitly requires full referrer URLs.',
            $findings
        );
    }

    private function checkFrameProtectionPolicy(
        string $targetId,
        string $url,
        ?string $xFrameOptions,
        ?string $csp,
        array &$findings
    ): void {
        $frameAncestors = null;

        if (
            $csp !== null &&
            trim($csp) !== ''
        ) {
            $directives =
                $this->parseContentSecurityPolicyDirectives(
                    $csp
                );

            $frameAncestors =
                $directives['frame-ancestors'] ?? null;
        }

        /*
         * CSP frame-ancestors is authoritative in modern browsers.
         * Do not report X-Frame-Options quality issues when an explicit
         * frame-ancestors directive is present.
         */
        if (is_array($frameAncestors)) {
            if ($frameAncestors === []) {
                $this->createFinding(
                    $targetId,
                    'security_header',
                    'Content-Security-Policy frame-ancestors appears invalid',
                    'The frame-ancestors directive was present without any source expression.',
                    'medium',
                    'medium',
                    [
                        'url' => $url,
                        'header' => 'content-security-policy',
                        'directive' => 'frame-ancestors',
                    ],
                    'Configure frame-ancestors with an explicit source list such as self, trusted origins, or none.',
                    $findings
                );

                return;
            }

            if (in_array('*', $frameAncestors, true)) {
                $this->createFinding(
                    $targetId,
                    'security_header',
                    'Content-Security-Policy frame-ancestors allows wildcard framing',
                    'The frame-ancestors directive permits arbitrary origins to frame this response.',
                    'medium',
                    'high',
                    [
                        'url' => $url,
                        'header' => 'content-security-policy',
                        'directive' => 'frame-ancestors',
                        'observed_value' =>
                            implode(' ', $frameAncestors),
                    ],
                    'Restrict frame-ancestors to trusted origins, self, or none according to application requirements.',
                    $findings
                );
            }

            return;
        }

        /*
         * Absence of X-Frame-Options alone is not reported. Not every
         * response requires clickjacking protection and CSP may be
         * application-dependent.
         */
        if ($xFrameOptions === null) {
            return;
        }

        $normalized = strtoupper(
            trim($xFrameOptions)
        );

        if (
            $normalized === 'DENY' ||
            $normalized === 'SAMEORIGIN'
        ) {
            return;
        }

        if (str_starts_with($normalized, 'ALLOW-FROM')) {
            $this->createFinding(
                $targetId,
                'security_header',
                'X-Frame-Options uses obsolete ALLOW-FROM directive',
                'The X-Frame-Options header uses the obsolete ALLOW-FROM directive, which is not consistently supported by modern browsers.',
                'low',
                'high',
                [
                    'url' => $url,
                    'header' => 'x-frame-options',
                    'value' => $xFrameOptions,
                ],
                'Use CSP frame-ancestors for origin-specific framing control, or use DENY/SAMEORIGIN where appropriate.',
                $findings
            );

            return;
        }

        $this->createFinding(
            $targetId,
            'security_header',
            'X-Frame-Options has unexpected value',
            'The X-Frame-Options header was present but did not contain a recognized DENY or SAMEORIGIN value.',
            'low',
            'high',
            [
                'url' => $url,
                'header' => 'x-frame-options',
                'value' => $xFrameOptions,
            ],
            'Use DENY or SAMEORIGIN, or use CSP frame-ancestors for modern framing control.',
            $findings
        );
    }

    private function checkContentSecurityPolicy(
        string $targetId,
        string $url,
        string $policy,
        array &$findings
    ): void {
        /*
         * Passive CSP V1 analysis.
         *
         * Only report directly observed high-signal directives.
         * This does not attempt browser-level CSP evaluation and does
         * not infer nonce/hash correctness.
         */
        $directives =
            $this->parseContentSecurityPolicyDirectives(
                $policy
            );

        $defaultSources =
            $directives['default-src'] ?? null;

        if (
            is_array($defaultSources) &&
            in_array('*', $defaultSources, true)
        ) {
            $this->createFinding(
                $targetId,
                'content_security_policy',
                'Content-Security-Policy default-src allows wildcard sources',
                'The observed Content-Security-Policy allows any source through default-src.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'directive' => 'default-src',
                    'observed_value' =>
                        implode(' ', $defaultSources),
                ],
                'Restrict default-src to the minimum trusted source set required by the application.',
                $findings
            );
        }

        $scriptSources =
            $directives['script-src']
            ?? $defaultSources;

        /*
         * If script-src is absent, default-src is its CSP fallback.
         * This lets us report only the effective source list we can
         * determine directly from the policy.
         */
        if (! is_array($scriptSources)) {
            return;
        }

        if (in_array('*', $scriptSources, true)) {
            $this->createFinding(
                $targetId,
                'content_security_policy',
                'Content-Security-Policy permits wildcard script sources',
                'The effective script source policy permits scripts from arbitrary origins.',
                'high',
                'high',
                [
                    'url' => $url,
                    'directive' =>
                        isset($directives['script-src'])
                            ? 'script-src'
                            : 'default-src',
                    'observed_value' =>
                        implode(' ', $scriptSources),
                ],
                'Restrict script sources to explicitly trusted origins and use nonces or hashes where appropriate.',
                $findings
            );
        }

        if (
            in_array(
                "'unsafe-inline'",
                $scriptSources,
                true
            )
        ) {
            $this->createFinding(
                $targetId,
                'content_security_policy',
                'Content-Security-Policy permits unsafe inline scripts',
                'The effective script source policy contains unsafe-inline, weakening script execution restrictions.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'directive' =>
                        isset($directives['script-src'])
                            ? 'script-src'
                            : 'default-src',
                    'token' => "'unsafe-inline'",
                ],
                'Remove unsafe-inline where practical and authorize required inline scripts with CSP nonces or hashes.',
                $findings
            );
        }

        if (
            in_array(
                "'unsafe-eval'",
                $scriptSources,
                true
            )
        ) {
            $this->createFinding(
                $targetId,
                'content_security_policy',
                'Content-Security-Policy permits unsafe script evaluation',
                'The effective script source policy contains unsafe-eval, allowing dynamic string-to-code evaluation.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'directive' =>
                        isset($directives['script-src'])
                            ? 'script-src'
                            : 'default-src',
                    'token' => "'unsafe-eval'",
                ],
                'Remove unsafe-eval and refactor code that requires dynamic string-to-code execution where practical.',
                $findings
            );
        }
    }

    private function checkServerDisclosure(
        string $targetId,
        string $url,
        array $headers,
        array &$findings
    ): void {
        $server = $this->header($headers, 'server');
        $poweredBy = $this->header($headers, 'x-powered-by');

        /*
         * A generic Server header such as "cloudflare" is not treated
         * as a vulnerability by itself. X-Powered-By is more actionable.
         */
        if ($poweredBy !== null) {
            $this->createFinding(
                $targetId,
                'information_disclosure',
                'Framework information disclosure',
                'The response exposes an X-Powered-By header that may reveal application/framework information.',
                'low',
                'high',
                [
                    'url' => $url,
                    'header' => 'x-powered-by',
                    'value' => $poweredBy,
                ],
                'Remove X-Powered-By where practical to minimize unnecessary technology disclosure.',
                $findings
            );
        }

        /*
         * A technology name alone is not treated as detailed disclosure.
         * Report only when the Server header exposes an explicit version.
         */
        if (
            $server !== null &&
            preg_match(
                '/\b(?:apache|nginx|iis|php|node|express|tomcat|jetty)(?:\/|\s+)[vV]?(\d+(?:\.\d+)+)\b/i',
                $server
            )
        ) {
            $this->createFinding(
                $targetId,
                'information_disclosure',
                'Detailed server version disclosure',
                'The response exposes a recognizable web-server technology together with an explicit version.',
                'low',
                'high',
                [
                    'url' => $url,
                    'header' => 'server',
                    'value' => $server,
                ],
                'Minimize unnecessary server version disclosure where practical.',
                $findings
            );
        }
    }

    private function checkCookies(
        string $targetId,
        string $url,
        string $scheme,
        array $rawHeaders,
        array &$findings
    ): void {
        $cookies = $rawHeaders['set-cookie'] ?? [];

        if (! is_array($cookies) || $cookies === []) {
            return;
        }

        foreach ($cookies as $cookie) {
            if (! is_string($cookie) || trim($cookie) === '') {
                continue;
            }

            $firstSegment = explode(';', $cookie, 2)[0] ?? '';

            $equalsPosition = strpos(
                $firstSegment,
                '='
            );

            if (
                $equalsPosition === false ||
                $equalsPosition < 1
            ) {
                continue;
            }

            $cookieName = trim(
                substr(
                    $firstSegment,
                    0,
                    $equalsPosition
                )
            );

            if ($cookieName === '') {
                continue;
            }

            $segments = array_map(
                'trim',
                explode(';', $cookie)
            );

            $secure = false;
            $httpOnly = false;
            $sameSite = null;
            $path = null;
            $domainPresent = false;

            foreach (array_slice($segments, 1) as $attribute) {
                if ($attribute === '') {
                    continue;
                }

                if (strcasecmp($attribute, 'secure') === 0) {
                    $secure = true;
                    continue;
                }

                if (strcasecmp($attribute, 'httponly') === 0) {
                    $httpOnly = true;
                    continue;
                }

                if (
                    preg_match(
                        '/^samesite\s*=\s*(strict|lax|none)$/i',
                        $attribute,
                        $match
                    )
                ) {
                    $sameSite = strtolower(
                        $match[1]
                    );

                    continue;
                }

                if (
                    preg_match(
                        '/^path\s*=\s*(.*)$/i',
                        $attribute,
                        $match
                    )
                ) {
                    $path = trim($match[1]);

                    continue;
                }

                if (
                    preg_match(
                        '/^domain\s*=/i',
                        $attribute
                    )
                ) {
                    $domainPresent = true;
                }
            }

            $this->checkCookiePrefixSemantics(
                $targetId,
                $url,
                $scheme,
                $cookieName,
                $secure,
                $path,
                $domainPresent,
                $findings
            );

            if ($scheme === 'https' && ! $secure) {
                $this->createFinding(
                    $targetId,
                    'cookie_security',
                    "Cookie {$cookieName} missing Secure attribute",
                    'A cookie was set over HTTPS without the Secure attribute.',
                    'medium',
                    'high',
                    [
                        'url' => $url,
                        'cookie' => $cookieName,
                        'attribute' => 'Secure',
                    ],
                    'Set the Secure attribute for cookies that should only travel over HTTPS.',
                    $findings
                );
            }

            if (! $httpOnly) {
                $this->createFinding(
                    $targetId,
                    'cookie_security',
                    "Cookie {$cookieName} missing HttpOnly attribute",
                    'A cookie was set without the HttpOnly attribute.',
                    'low',
                    'high',
                    [
                        'url' => $url,
                        'cookie' => $cookieName,
                        'attribute' => 'HttpOnly',
                    ],
                    'Use HttpOnly for cookies that do not need client-side JavaScript access.',
                    $findings
                );
            }

            if ($sameSite === null) {
                $this->createFinding(
                    $targetId,
                    'cookie_security',
                    "Cookie {$cookieName} missing SameSite attribute",
                    'A cookie was set without an explicit SameSite attribute.',
                    'low',
                    'medium',
                    [
                        'url' => $url,
                        'cookie' => $cookieName,
                        'attribute' => 'SameSite',
                    ],
                    'Configure an appropriate SameSite policy based on the application flow.',
                    $findings
                );
            }

            if (
                $sameSite === 'none' &&
                ! $secure
            ) {
                $this->createFinding(
                    $targetId,
                    'cookie_security',
                    "Cookie {$cookieName} uses SameSite=None without Secure",
                    'The cookie uses SameSite=None but does not include the Secure attribute.',
                    'medium',
                    'high',
                    [
                        'url' => $url,
                        'cookie' => $cookieName,
                        'same_site' => 'none',
                        'secure' => false,
                    ],
                    'Cookies using SameSite=None should also include the Secure attribute.',
                    $findings
                );
            }
        }
    }

    private function checkCookiePrefixSemantics(
        string $targetId,
        string $url,
        string $scheme,
        string $cookieName,
        bool $secure,
        ?string $path,
        bool $domainPresent,
        array &$findings
    ): void {
        /*
         * Cookie Prefix Semantics V1
         *
         * Prefix matching is intentionally case-sensitive.
         * Only cookies explicitly opting into __Secure- or __Host-
         * semantics are evaluated here.
         */
        if (
            str_starts_with(
                $cookieName,
                '__Secure-'
            ) &&
            $scheme !== 'https'
        ) {
            $this->createFinding(
                $targetId,
                'cookie_security',
                "Cookie {$cookieName} violates __Secure- secure-origin requirement",
                'The cookie uses the __Secure- prefix but was observed on a non-HTTPS response.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'cookie' => $cookieName,
                    'prefix' => '__Secure-',
                    'requirement' => 'HTTPS origin',
                    'observed_scheme' => $scheme,
                ],
                'Set cookies using the __Secure- prefix only from a secure HTTPS origin.',
                $findings
            );
        }

        if (
            str_starts_with(
                $cookieName,
                '__Secure-'
            ) &&
            ! $secure
        ) {
            $this->createFinding(
                $targetId,
                'cookie_security',
                "Cookie {$cookieName} violates __Secure- prefix requirements",
                'The cookie uses the __Secure- prefix but does not include the Secure attribute.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'cookie' => $cookieName,
                    'prefix' => '__Secure-',
                    'secure' => false,
                ],
                'Add the Secure attribute to cookies using the __Secure- prefix and set them from a secure origin.',
                $findings
            );
        }

        if (! str_starts_with(
            $cookieName,
            '__Host-'
        )) {
            return;
        }

        if ($scheme !== 'https') {
            $this->createFinding(
                $targetId,
                'cookie_security',
                "Cookie {$cookieName} violates __Host- secure-origin requirement",
                'The cookie uses the __Host- prefix but was observed on a non-HTTPS response.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'cookie' => $cookieName,
                    'prefix' => '__Host-',
                    'requirement' => 'HTTPS origin',
                    'observed_scheme' => $scheme,
                ],
                'Set cookies using the __Host- prefix only from a secure HTTPS origin.',
                $findings
            );
        }

        if (! $secure) {
            $this->createFinding(
                $targetId,
                'cookie_security',
                "Cookie {$cookieName} violates __Host- Secure requirement",
                'The cookie uses the __Host- prefix but does not include the Secure attribute.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'cookie' => $cookieName,
                    'prefix' => '__Host-',
                    'requirement' => 'Secure',
                    'secure' => false,
                ],
                'Add the Secure attribute to cookies using the __Host- prefix.',
                $findings
            );
        }

        if ($path !== '/') {
            $this->createFinding(
                $targetId,
                'cookie_security',
                "Cookie {$cookieName} violates __Host- Path requirement",
                'The cookie uses the __Host- prefix but its Path attribute is not exactly /.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'cookie' => $cookieName,
                    'prefix' => '__Host-',
                    'requirement' => 'Path=/',
                    'observed_path' => $path,
                ],
                'Set Path=/ for cookies using the __Host- prefix.',
                $findings
            );
        }

        if ($domainPresent) {
            $this->createFinding(
                $targetId,
                'cookie_security',
                "Cookie {$cookieName} violates __Host- Domain requirement",
                'The cookie uses the __Host- prefix but includes a Domain attribute.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'cookie' => $cookieName,
                    'prefix' => '__Host-',
                    'requirement' => 'Domain absent',
                    'domain_attribute_present' => true,
                ],
                'Remove the Domain attribute from cookies using the __Host- prefix.',
                $findings
            );
        }
    }

    private function checkCors(
        string $targetId,
        string $url,
        array $headers,
        array &$findings
    ): void {
        $acao = $this->header($headers, 'access-control-allow-origin');
        $acac = $this->header($headers, 'access-control-allow-credentials');

        if ($acao === '*') {
            $this->createFinding(
                $targetId,
                'cors',
                'Wildcard CORS policy observed',
                'The response permits cross-origin access from any origin.',
                'low',
                'high',
                [
                    'url' => $url,
                    'header' => 'access-control-allow-origin',
                    'value' => $acao,
                ],
                'Restrict allowed origins when the application does not intentionally require public cross-origin access.',
                $findings
            );
        }

        if ($acao === '*' && strtolower(trim((string) $acac)) === 'true') {
            $this->createFinding(
                $targetId,
                'cors',
                'Inconsistent credentialed wildcard CORS configuration',
                'The response advertises wildcard Access-Control-Allow-Origin together with credential support.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'allow_origin' => $acao,
                    'allow_credentials' => $acac,
                ],
                'Use an explicit trusted origin list and review credentialed CORS behavior.',
                $findings
            );
        }
    }

    private function probeCorsOriginReflection(
        string $targetId,
        string $url,
        string $host,
        int $port,
        array $resolvedIps,
        array &$findings
    ): void {
        /*
         * CORS Reflection Probe V1
         *
         * One bounded secondary GET to the same authorized URL.
         * The already validated/pinned destination set is reused.
         * Redirects remain disabled by the transport.
         */
        $probeOrigin = 'https://cors-probe.invalid';

        try {
            $result = $this->httpTransport->get(
                $url,
                $host,
                $port,
                $resolvedIps,
                self::CONNECT_TIMEOUT_SECONDS,
                self::REQUEST_TIMEOUT_SECONDS,
                self::MAX_RESPONSE_BYTES,
                [
                    'Origin' => $probeOrigin,
                ]
            );
        } catch (\Throwable) {
            /*
             * Optional intelligence must not turn an otherwise valid
             * assessment into a scanner failure.
             */
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            return;
        }

        $rawHeaders = $result['headers'] ?? null;

        if (! is_array($rawHeaders)) {
            return;
        }

        $headers = $this->flattenHeaders(
            $this->normalizeRawHeaders($rawHeaders)
        );

        $allowOrigin = $this->header(
            $headers,
            'access-control-allow-origin'
        );

        if (
            $allowOrigin === null ||
            trim($allowOrigin) !== $probeOrigin
        ) {
            return;
        }

        $allowCredentials = strtolower(
            trim(
                (string) (
                    $this->header(
                        $headers,
                        'access-control-allow-credentials'
                    ) ?? ''
                )
            )
        ) === 'true';

        if ($allowCredentials) {
            $this->createFinding(
                $targetId,
                'cors',
                'Credentialed arbitrary CORS origin reflection observed',
                'The application reflected a synthetic arbitrary Origin and also permitted credentialed cross-origin requests.',
                'high',
                'high',
                [
                    'url' => $url,
                    'probe_origin' => $probeOrigin,
                    'access_control_allow_origin' =>
                        $allowOrigin,
                    'access_control_allow_credentials' =>
                        'true',
                    'probe_method' => 'GET',
                ],
                'Use an explicit allowlist of trusted origins and do not enable credentialed CORS for arbitrary reflected origins.',
                $findings
            );

            return;
        }

        $this->createFinding(
            $targetId,
            'cors',
            'Arbitrary CORS origin reflection observed',
            'The application reflected a synthetic arbitrary Origin in Access-Control-Allow-Origin.',
            'medium',
            'high',
            [
                'url' => $url,
                'probe_origin' => $probeOrigin,
                'access_control_allow_origin' =>
                    $allowOrigin,
                'access_control_allow_credentials' =>
                    'false',
                'probe_method' => 'GET',
            ],
            'Use an explicit allowlist of trusted origins instead of reflecting arbitrary Origin values.',
            $findings
        );
    }

    private function checkHttpMethods(
        string $targetId,
        string $url,
        array $headers,
        array &$findings
    ): void {
        $allow = $this->header($headers, 'allow');

        if ($allow === null) {
            return;
        }

        $methods = array_filter(array_map(
            fn ($method) => strtoupper(trim($method)),
            explode(',', $allow)
        ));

        $dangerous = array_values(array_intersect(
            $methods,
            ['TRACE', 'CONNECT']
        ));

        if ($dangerous !== []) {
            $this->createFinding(
                $targetId,
                'http_methods',
                'Potentially risky HTTP methods advertised',
                'The response advertises TRACE or CONNECT in the Allow header.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'allow' => $allow,
                    'methods' => $dangerous,
                ],
                'Disable HTTP methods that are not required by the application or infrastructure.',
                $findings
            );
        }
    }

    /**
     * Analyze a bounded redirect chain without allowing the HTTP client
     * to follow redirects automatically.
     *
     * Only same-host destinations remain inside the authorized target
     * boundary. Cross-host redirects are recorded but never contacted.
     */
    private function performPinnedRequest(
        string $url,
        string $host,
        int $port,
        array $resolvedIps
    ): array {
        return $this->httpTransport->get(
            $url,
            $host,
            $port,
            $resolvedIps,
            self::CONNECT_TIMEOUT_SECONDS,
            self::REQUEST_TIMEOUT_SECONDS,
            self::MAX_RESPONSE_BYTES
        );
    }


private function analyzeRedirectChain(
        string $initialUrl,
        string $authorizedHost,
        int $initialStatus,
        array $initialHeaders
    ): array {
        $maxHops = 5;

        $chain = [];
        $visited = [
            $initialUrl => true,
        ];

        $currentUrl = $initialUrl;
        $currentStatus = $initialStatus;
        $currentHeaders = $initialHeaders;

        for ($hop = 1; $hop <= $maxHops; $hop++) {
            if (
                $currentStatus < 300 ||
                $currentStatus >= 400
            ) {
                break;
            }

            $location = $this->header(
                $currentHeaders,
                'location'
            );

            if ($location === null || trim($location) === '') {
                break;
            }

            try {
                $nextUrl = $this->resolveRedirectUrl(
                    $currentUrl,
                    $location
                );
            } catch (RuntimeException $exception) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => $exception->getMessage(),
                ];

                break;
            }

            if (isset($visited[$nextUrl])) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'to' => $nextUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => 'Redirect loop detected.',
                ];

                break;
            }

            $parsed = parse_url($nextUrl);

            if (
                ! is_array($parsed) ||
                ! isset($parsed['scheme'], $parsed['host'])
            ) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'to' => $nextUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => 'Resolved redirect URL is invalid.',
                ];

                break;
            }

            $scheme = strtolower(
                (string) $parsed['scheme']
            );

            if (! in_array($scheme, ['http', 'https'], true)) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'to' => $nextUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => 'Redirect uses a non-HTTP(S) scheme.',
                ];

                break;
            }

            $host = $this->normalizeHost(
                (string) $parsed['host']
            );

            if (
                ! $this->hostsEquivalent(
                    $authorizedHost,
                    $host
                )
            ) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'to' => $nextUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => 'Redirect leaves the authorized target hostname.',
                ];

                break;
            }

            $port = isset($parsed['port'])
                ? (int) $parsed['port']
                : ($scheme === 'https' ? 443 : 80);

            if ($port < 1 || $port > 65535) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'to' => $nextUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => 'Redirect port is outside the valid TCP port range.',
                ];

                break;
            }

            try {
                /*
                 * Re-resolve every redirect hop.
                 * A hostname becoming private/reserved between requests
                 * is therefore blocked before any connection is made.
                 */
                $resolvedIps = $this->resolveAndValidateHost(
                    $host
                );
            } catch (RuntimeException $exception) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'to' => $nextUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => 'Redirect destination failed network safety validation.',
                ];

                break;
            }

            $visited[$nextUrl] = true;

            $requestResult = $this->performPinnedRequest(
                $nextUrl,
                $host,
                $port,
                $resolvedIps
            );

            if (! $requestResult['ok']) {
                $chain[] = [
                    'hop' => $hop,
                    'from' => $currentUrl,
                    'to' => $nextUrl,
                    'status' => $currentStatus,
                    'location' => $location,
                    'followed' => false,
                    'reason' => 'Redirect destination request failed.',
                    'error' => $requestResult['error'],
                ];

                break;
            }

            $durationMs = $requestResult['duration_ms'];

            $nextRawHeaders = $this->normalizeRawHeaders(
                $requestResult['headers']
            );

            $nextHeaders = $this->flattenHeaders(
                $nextRawHeaders
            );

            $chain[] = [
                'hop' => $hop,
                'from' => $currentUrl,
                'to' => $nextUrl,
                'status' => $currentStatus,
                'destination_status' => $requestResult['status'],
                'location' => $location,
                'followed' => true,
                'resolved_ips' => $resolvedIps,
                'duration_ms' => $durationMs,
            ];

            $currentUrl = $nextUrl;
            $currentStatus = $requestResult['status'];
            $currentHeaders = $nextHeaders;
        }

        return $chain;
    }

    /**
     * Resolve an RFC-style HTTP Location value against the current URL.
     *
     * Guzzle's PSR-7 URI resolver is already part of Laravel's HTTP stack
     * and avoids implementing unsafe ad-hoc relative URL resolution.
     */
    private function resolveRedirectUrl(
        string $baseUrl,
        string $location
    ): string {
        $location = trim($location);

        if ($location === '') {
            throw new RuntimeException(
                'Redirect Location header is empty.'
            );
        }

        if (
            str_contains($location, '\\') ||
            preg_match('/[\x00-\x20\x7f]/', $location)
        ) {
            throw new RuntimeException(
                'Redirect Location contains unsafe or ambiguous characters.'
            );
        }

        try {
            $base = new \GuzzleHttp\Psr7\Uri(
                $baseUrl
            );

            $relative = new \GuzzleHttp\Psr7\Uri(
                $location
            );

            $resolved = \GuzzleHttp\Psr7\UriResolver::resolve(
                $base,
                $relative
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Redirect Location could not be resolved safely.'
            );
        }

        $scheme = strtolower(
            $resolved->getScheme()
        );

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(
                'Redirect uses a non-HTTP(S) scheme.'
            );
        }

        if ($resolved->getUserInfo() !== '') {
            throw new RuntimeException(
                'Redirect URLs containing user information are not allowed.'
            );
        }

        if ($resolved->getFragment() !== '') {
            /*
             * Fragments are never sent over HTTP and are removed from
             * the scanner's canonical request URL.
             */
            $resolved = $resolved->withFragment('');
        }

        $host = $this->normalizeHost(
            $resolved->getHost()
        );

        if ($host === '') {
            throw new RuntimeException(
                'Redirect destination hostname is empty.'
            );
        }

        $port = $resolved->getPort();

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new RuntimeException(
                'Redirect port is outside the valid TCP port range.'
            );
        }

        $authorityHost = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        )
            ? "[{$host}]"
            : $host;

        $effectivePort = $port
            ?? ($scheme === 'https' ? 443 : 80);

        $defaultPort = $scheme === 'https'
            ? 443
            : 80;

        $authority = $authorityHost;

        if ($effectivePort !== $defaultPort) {
            $authority .= ':' . $effectivePort;
        }

        $path = $resolved->getPath();

        if ($path === '') {
            $path = '/';
        }

        $url =
            $scheme . '://' .
            $authority .
            $path;

        if ($resolved->getQuery() !== '') {
            $url .= '?' . $resolved->getQuery();
        }

        return $url;
    }

    private function recordRedirectFinding(
        string $targetId,
        string $url,
        string $location,
        array &$findings
    ): void {
        $redirectUrl = parse_url($location);

        if (
            is_array($redirectUrl) &&
            isset($redirectUrl['scheme']) &&
            ! in_array(strtolower($redirectUrl['scheme']), ['http', 'https'], true)
        ) {
            $this->createFinding(
                $targetId,
                'redirect',
                'Unexpected redirect scheme observed',
                'The HTTP response contains a Location header using a non-HTTP(S) scheme.',
                'medium',
                'high',
                [
                    'url' => $url,
                    'location' => $location,
                ],
                'Review redirect generation and allow only expected HTTP(S) destinations.',
                $findings
            );
        }
    }

    private function checkTlsCertificateFindings(
        string $targetId,
        string $url,
        array $tls,
        array &$findings
    ): void {
        /*
         * Only verified/parsed certificate evidence can create
         * certificate findings. A failed TLS connection is not enough
         * evidence to distinguish certificate failure from network,
         * protocol, routing, or server availability problems.
         */
        if (
            ($tls['enabled'] ?? false) !== true ||
            ($tls['status'] ?? null) !== 'verified'
        ) {
            return;
        }

        $certificate = $tls['certificate'] ?? null;

        if (! is_array($certificate)) {
            return;
        }

        $evidence = [
            'url' => $url,
            'subject' =>
                $certificate['subject'] ?? null,
            'issuer' =>
                $certificate['issuer'] ?? null,
            'valid_from' =>
                $certificate['valid_from'] ?? null,
            'valid_to' =>
                $certificate['valid_to'] ?? null,
            'days_remaining' =>
                $certificate['days_remaining'] ?? null,
            'sha256_fingerprint' =>
                $certificate[
                    'sha256_fingerprint'
                ] ?? null,
        ];

        if (($certificate['expired'] ?? false) === true) {
            $this->createFinding(
                $targetId,
                'tls_certificate',
                'TLS certificate has expired',
                'The observed TLS certificate is past its validity end date.',
                'high',
                'high',
                $evidence + [
                    'expired' => true,
                    'not_yet_valid' => false,
                ],
                'Replace or renew the TLS certificate and deploy the valid certificate chain.',
                $findings
            );

            /*
             * An expired certificate may also have a negative
             * days_remaining value. Do not create the upcoming-expiry
             * warning for the same observation.
             */
            return;
        }

        if (
            ($certificate['not_yet_valid'] ?? false)
            === true
        ) {
            $this->createFinding(
                $targetId,
                'tls_certificate',
                'TLS certificate is not yet valid',
                'The observed TLS certificate validity period has not started yet.',
                'high',
                'high',
                $evidence + [
                    'expired' => false,
                    'not_yet_valid' => true,
                ],
                'Deploy a certificate whose validity period is currently active and verify system time and certificate deployment.',
                $findings
            );

            return;
        }

        $daysRemaining =
            $certificate['days_remaining'] ?? null;

        if (
            is_int($daysRemaining) &&
            $daysRemaining >= 0 &&
            $daysRemaining <= 14
        ) {
            $this->createFinding(
                $targetId,
                'tls_certificate',
                'TLS certificate expires soon',
                'The observed TLS certificate is valid but is within 14 days of expiration.',
                'low',
                'high',
                $evidence + [
                    'expired' => false,
                    'not_yet_valid' => false,
                    'threshold_days' => 14,
                ],
                'Renew and deploy the TLS certificate before its expiration date.',
                $findings
            );
        }
    }

    private function inspectTls(
        string $host,
        int $port,
        array $resolvedIps
    ): array {
        return $this->tlsIntelligenceEngine->inspect(
            $host,
            $port,
            $resolvedIps
        );
    }

    private function createFinding(
        string $targetId,
        string $type,
        string $title,
        string $description,
        string $severity,
        string $confidence,
        array $evidenceData,
        string $remediation,
        array &$findings
    ): void {
        /*
         * Fingerprint must represent the identity of the finding,
         * not volatile observation data such as timestamps, response
         * duration, or changing evidence values.
         */
        $fingerprintPayload = [
            'target_id' => $targetId,
            'type' => $type,
            'title' => $title,
        ];

        $fingerprint = hash(
            'sha256',
            json_encode(
                $fingerprintPayload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );

        foreach ($findings as $existing) {
            if (($existing['fingerprint'] ?? null) === $fingerprint) {
                return;
            }
        }

        $storedEvidence = $evidenceData + [
            'fingerprint' => $fingerprint,
            'observed_at' => $this->utcNowIso8601(),
        ];

        $findings[] = [
            'fingerprint' => $fingerprint,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'confidence' => $confidence,
            'evidence_data' => $storedEvidence,
            'remediation' => $remediation,
        ];
    }

    private function utcNowIso8601(): string
    {
        return (new \DateTimeImmutable(
            'now',
            new \DateTimeZone('UTC')
        ))->format('Y-m-d\TH:i:sP');
    }

    private function normalizeRawHeaders(
        array $headers
    ): array {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $key = strtolower(
                trim((string) $name)
            );

            if ($key === '') {
                continue;
            }

            if (is_array($values)) {
                $normalized[$key] = array_values(
                    array_map(
                        static fn ($value) => (string) $value,
                        $values
                    )
                );

                continue;
            }

            $normalized[$key] = [
                (string) $values,
            ];
        }

        return $normalized;
    }

    private function flattenHeaders(
        array $rawHeaders
    ): array {
        $flattened = [];

        foreach ($rawHeaders as $name => $values) {
            if (! is_array($values)) {
                $values = [
                    (string) $values,
                ];
            }

            /*
             * Set-Cookie is intentionally not flattened for semantic use.
             * A compatibility string is still exposed in the generic header
             * map, while cookie analysis uses normalizeRawHeaders() directly.
             */
            $flattened[$name] = implode(
                ', ',
                array_map(
                    static fn ($value) => (string) $value,
                    $values
                )
            );
        }

        return $flattened;
    }

    private function header(array $headers, string $name): ?string
    {
        return $headers[strtolower($name)] ?? null;
    }
}
