<?php

namespace App\Services\Scanner;

use App\Models\Target;
use RuntimeException;

final class ApiSecurityInspectionService
{
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const REQUEST_TIMEOUT_SECONDS = 10;
    private const MAX_RESPONSE_BYTES = 2_097_152;

    public function __construct(
        private readonly HttpTransport $httpTransport,
        private readonly DnsResolver $dnsResolver,
        private readonly ApiSecurityIntelligenceEngine $intelligenceEngine,
    ) {
    }

    public function inspect(Target $target): array
    {
        $canonical = $this->canonicalizeTarget($target);

        $url = $canonical['url'];
        $host = $canonical['host'];
        $scheme = $canonical['scheme'];
        $port = $canonical['port'];

        $ips = $this->resolveAndValidateHost($host);

        $get = $this->httpTransport->get(
            $url,
            $host,
            $port,
            $ips,
            self::CONNECT_TIMEOUT_SECONDS,
            self::REQUEST_TIMEOUT_SECONDS,
            self::MAX_RESPONSE_BYTES,
            [
                'Accept' =>
                    'application/json, application/problem+json, */*',
                'User-Agent' =>
                    'CrypticX-Lab-API-Inspector/1.0',
            ]
        );

        if (! ($get['ok'] ?? false)) {
            throw new RuntimeException(
                'The API endpoint request failed.'
            );
        }

        $headers = $this->normalizeHeaders(
            $get['headers'] ?? []
        );

        /*
         * V1 is GET-only. No OPTIONS/preflight, endpoint discovery,
         * authentication attempts, fuzzing, or state-changing methods.
         */
        $allow = $this->header(
            $headers,
            'allow'
        );

        $contentType = $this->header(
            $headers,
            'content-type'
        );

        $securityHeaders = $this->securityHeaders(
            $headers
        );

        $cors = [
            'allow_origin' => $this->header(
                $headers,
                'access-control-allow-origin'
            ),
            'allow_credentials' => $this->header(
                $headers,
                'access-control-allow-credentials'
            ),
            'allow_methods' => $this->header(
                $headers,
                'access-control-allow-methods'
            ),
            'allow_headers' => $this->header(
                $headers,
                'access-control-allow-headers'
            ),
        ];

        $configuration = [
            'https' => $scheme === 'https',
            'json_response' => false,

            'cache_control' => $this->header(
                $headers,
                'cache-control'
            ),

            'www_authenticate' => $this->header(
                $headers,
                'www-authenticate'
            ),

            'server' => $this->header(
                $headers,
                'server'
            ),

            'powered_by' => $this->header(
                $headers,
                'x-powered-by'
            ),

            'api_version' =>
                $this->header(
                    $headers,
                    'api-version'
                ) ??
                $this->header(
                    $headers,
                    'x-api-version'
                ),

            'content_type_options' => $this->header(
                $headers,
                'x-content-type-options'
            ),

            'hsts' => $this->header(
                $headers,
                'strict-transport-security'
            ),
        ];

        $intelligence = $this->intelligenceEngine->analyze([
            'status' => (int) ($get['status'] ?? 0),
            'content_type' => $contentType,
            'allow_header' => $allow,

            'allow_origin' => $cors['allow_origin'],
            'allow_credentials' =>
                $cors['allow_credentials'],

            'www_authenticate' =>
                $configuration['www_authenticate'],

            'cache_control' =>
                $configuration['cache_control'],

            'server' =>
                $configuration['server'],

            'powered_by' =>
                $configuration['powered_by'],

            'api_version' =>
                $configuration['api_version'],
        ]);

        $jsonLike =
            $intelligence['classification']['json_response'];

        $configuration['json_response'] = $jsonLike;

        return [
            'target_id' => (string) $target->id,
            'url' => $url,
            'hostname' => $host,
            'resolved_ips' => $ips,

            'endpoint' => [
                'status' => (int) ($get['status'] ?? 0),
                'successful' =>
                    (bool) ($get['successful'] ?? false),
                'duration_ms' =>
                    (int) ($get['duration_ms'] ?? 0),
                'content_type' => $contentType,
                'content_length' => $this->header(
                    $headers,
                    'content-length'
                ),
                'location' => $this->header(
                    $headers,
                    'location'
                ),
                'json_response' => $jsonLike,
            ],

            'methods' => [
                /*
                 * Compatibility field retained.
                 * V1 never actively executes OPTIONS.
                 */
                'options_status' => null,
                'allow_header' =>
                    $intelligence['methods']['allow_header'],
                'advertised' =>
                    $intelligence['methods']['advertised'],
                'risky' =>
                    $intelligence['methods']['risky'],
            ],

            'security_headers' => $securityHeaders,
            'cors' => $cors,
            'configuration' => $configuration,

            'intelligence' => $intelligence,
            'findings' => $intelligence['findings'],
            'finding_count' =>
                $intelligence['finding_count'],
        ];
    }

    private function canonicalizeTarget(Target $target): array
    {
        $url = trim((string) $target->url);

        $parsed = parse_url($url);

        if (
            ! is_array($parsed) ||
            ! isset($parsed['scheme'], $parsed['host'])
        ) {
            throw new RuntimeException(
                'Authorized target URL is invalid.'
            );
        }

        $scheme = strtolower($parsed['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(
                'Only HTTP and HTTPS targets are allowed.'
            );
        }

        if (
            isset($parsed['user']) ||
            isset($parsed['pass'])
        ) {
            throw new RuntimeException(
                'Target URLs containing credentials are not allowed.'
            );
        }

        $host = strtolower(
            trim($parsed['host'], '[]')
        );

        if ($host === '') {
            throw new RuntimeException(
                'Authorized target hostname is empty.'
            );
        }

        $port = isset($parsed['port'])
            ? (int) $parsed['port']
            : ($scheme === 'https' ? 443 : 80);

        if (
            strtolower((string) $target->hostname) !== $host
        ) {
            throw new RuntimeException(
                'Target URL hostname does not match the authorized target hostname.'
            );
        }

        if (
            strtolower((string) $target->scheme) !== $scheme
        ) {
            throw new RuntimeException(
                'Target URL scheme does not match the authorized target scheme.'
            );
        }

        $authorizedPort = $target->port !== null
            ? (int) $target->port
            : ($scheme === 'https' ? 443 : 80);

        if ($authorizedPort !== $port) {
            throw new RuntimeException(
                'Target URL port does not match the authorized target port.'
            );
        }

        return [
            'url' => $url,
            'host' => $host,
            'scheme' => $scheme,
            'port' => $port,
        ];
    }

    private function resolveAndValidateHost(
        string $hostname
    ): array {
        if (
            filter_var(
                $hostname,
                FILTER_VALIDATE_IP
            )
        ) {
            $ips = [$hostname];
        } else {
            $ips = $this->dnsResolver->resolve(
                $hostname
            );
        }

        $validated = [];

        foreach ($ips as $ip) {
            if (! is_string($ip) || $ip === '') {
                continue;
            }

            $this->assertPublicIp($ip);
            $validated[] = $ip;
        }

        $validated = array_values(
            array_unique($validated)
        );

        if ($validated === []) {
            throw new RuntimeException(
                'API hostname did not resolve to a usable public IP.'
            );
        }

        return $validated;
    }

    private function assertPublicIp(string $ip): void
    {
        if (
            ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE
            )
        ) {
            throw new RuntimeException(
                'Private, reserved, loopback, link-local, or unsafe IP targets are not allowed.'
            );
        }
    }

    private function normalizeHeaders(
        array $headers
    ): array {
        $normalized = [];

        foreach ($headers as $name => $values) {
            if (! is_string($name)) {
                continue;
            }

            $normalized[strtolower($name)] =
                is_array($values)
                    ? implode(', ', $values)
                    : (string) $values;
        }

        return $normalized;
    }

    private function securityHeaders(
        array $headers
    ): array {
        /*
         * Presence inventory only. Missing entries are not themselves
         * vulnerability findings.
         */
        $definitions = [
            'strict-transport-security'
                => 'Strict-Transport-Security',

            'content-security-policy'
                => 'Content-Security-Policy',

            'x-content-type-options'
                => 'X-Content-Type-Options',

            'referrer-policy'
                => 'Referrer-Policy',

            'permissions-policy'
                => 'Permissions-Policy',
        ];

        $results = [];

        foreach ($definitions as $key => $name) {
            $value = $this->header(
                $headers,
                $key
            );

            $results[] = [
                'name' => $name,
                'present' => $value !== null,
                'value' => $value,
            ];
        }

        return $results;
    }

    private function header(
        array $headers,
        string $name
    ): ?string {
        $value =
            $headers[strtolower($name)] ?? null;

        return is_string($value) &&
            trim($value) !== ''
                ? $value
                : null;
    }
}
