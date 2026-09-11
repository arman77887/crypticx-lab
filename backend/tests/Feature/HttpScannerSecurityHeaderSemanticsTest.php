<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use PHPUnit\Framework\TestCase;

class HttpScannerSecurityHeaderSemanticsTest extends TestCase
{
    private function scanHeaders(array $headers): array
    {
        $transport = new class($headers) implements HttpTransport {
            public function __construct(
                private readonly array $headers
            ) {
            }

            public function get(
                string $url,
                string $host,
                int $port,
                array $resolvedIps,
                int $connectTimeoutSeconds,
                int $requestTimeoutSeconds,
                int $maxResponseBytes,
                array $requestHeaders = []
            ): array {
                return [
                    'ok' => true,
                    'status' => 200,
                    'successful' => true,
                    'headers' => $this->headers,
                    'duration_ms' => 1,
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $dnsResolver = new class implements DnsResolver {
            public function resolve(string $host): array
            {
                return ['8.8.8.8'];
            }
        };

        $recordResolver = new class implements DnsRecordResolver {
            public function query(
                string $hostname,
                int $type
            ): array {
                if ($type === DNS_A) {
                    return [[
                        'host' => $hostname,
                        'type' => 'A',
                        'ttl' => 300,
                        'ip' => '8.8.8.8',
                    ]];
                }

                return [];
            }
        };

        $engine = new HttpScannerEngine(
            $transport,
            $dnsResolver,
            new DnsIntelligenceEngine(
                $recordResolver
            )
        );

        $result = $engine->scan([
            'contract_version' => 1,
            'assessment_id' =>
                'assessment-security-header-v2',
            'target' => [
                'id' => 'target-security-header-v2',
                'url' => 'http://example.test/',
                'hostname' => 'example.test',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        return array_values(
            array_filter(
                $result['findings'] ?? [],
                static fn (array $finding): bool =>
                    ($finding['type'] ?? null) ===
                    'security_header'
            )
        );
    }

    private function titles(array $findings): array
    {
        return array_values(
            array_map(
                static fn (array $finding): string =>
                    (string) ($finding['title'] ?? ''),
                $findings
            )
        );
    }

    private function baselineHeaders(): array
    {
        return [
            'content-security-policy' => [
                "default-src 'self'",
            ],
            'x-content-type-options' => [
                'nosniff',
            ],
            'referrer-policy' => [
                'strict-origin-when-cross-origin',
            ],
        ];
    }

    public function test_missing_permissions_policy_is_not_reported(): void
    {
        $titles = $this->titles(
            $this->scanHeaders(
                $this->baselineHeaders()
            )
        );

        $this->assertNotContains(
            'Permissions-Policy header missing',
            $titles
        );
    }

    public function test_unsafe_url_referrer_policy_is_reported(): void
    {
        $headers = $this->baselineHeaders();
        $headers['referrer-policy'] = ['unsafe-url'];

        $findings = $this->scanHeaders($headers);
        $titles = $this->titles($findings);

        $this->assertContains(
            'Referrer-Policy permits full URL referrer disclosure',
            $titles
        );
    }

    public function test_strict_referrer_policy_has_no_semantic_finding(): void
    {
        $titles = $this->titles(
            $this->scanHeaders(
                $this->baselineHeaders()
            )
        );

        $this->assertNotContains(
            'Referrer-Policy permits full URL referrer disclosure',
            $titles
        );

        $this->assertNotContains(
            'Referrer-Policy has no recognized policy',
            $titles
        );
    }

    public function test_unrecognized_referrer_policy_is_reported(): void
    {
        $headers = $this->baselineHeaders();
        $headers['referrer-policy'] = [
            'definitely-not-a-policy',
        ];

        $titles = $this->titles(
            $this->scanHeaders($headers)
        );

        $this->assertContains(
            'Referrer-Policy has no recognized policy',
            $titles
        );
    }

    public function test_csp_wildcard_frame_ancestors_is_reported(): void
    {
        $headers = $this->baselineHeaders();
        $headers['content-security-policy'] = [
            "default-src 'self'; frame-ancestors *",
        ];

        $titles = $this->titles(
            $this->scanHeaders($headers)
        );

        $this->assertContains(
            'Content-Security-Policy frame-ancestors allows wildcard framing',
            $titles
        );
    }

    public function test_csp_none_frame_ancestors_is_not_reported(): void
    {
        $headers = $this->baselineHeaders();
        $headers['content-security-policy'] = [
            "default-src 'self'; frame-ancestors 'none'",
        ];

        $titles = $this->titles(
            $this->scanHeaders($headers)
        );

        $this->assertNotContains(
            'Content-Security-Policy frame-ancestors allows wildcard framing',
            $titles
        );

        $this->assertNotContains(
            'X-Frame-Options has unexpected value',
            $titles
        );
    }

    public function test_obsolete_allow_from_is_reported_without_frame_ancestors(): void
    {
        $headers = $this->baselineHeaders();
        $headers['x-frame-options'] = [
            'ALLOW-FROM https://example.org',
        ];

        $titles = $this->titles(
            $this->scanHeaders($headers)
        );

        $this->assertContains(
            'X-Frame-Options uses obsolete ALLOW-FROM directive',
            $titles
        );
    }

    public function test_valid_xfo_is_not_reported(): void
    {
        $headers = $this->baselineHeaders();
        $headers['x-frame-options'] = [
            'DENY',
        ];

        $titles = $this->titles(
            $this->scanHeaders($headers)
        );

        $this->assertNotContains(
            'X-Frame-Options has unexpected value',
            $titles
        );

        $this->assertNotContains(
            'X-Frame-Options uses obsolete ALLOW-FROM directive',
            $titles
        );
    }

    public function test_missing_xfo_without_frame_ancestors_is_not_reported(): void
    {
        $titles = $this->titles(
            $this->scanHeaders(
                $this->baselineHeaders()
            )
        );

        $this->assertNotContains(
            'X-Frame-Options header missing',
            $titles
        );

        $this->assertNotContains(
            'X-Frame-Options has unexpected value',
            $titles
        );

        $this->assertNotContains(
            'X-Frame-Options uses obsolete ALLOW-FROM directive',
            $titles
        );
    }
}
