<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use Tests\TestCase;

class HttpScannerCspQualityTest extends TestCase
{
    private function scanWithCsp(string $csp): array
    {
        $transport = new class($csp)
            implements HttpTransport
        {
            public function __construct(
                private readonly string $csp
            ) {}

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
                    'duration_ms' => 5,
                    'headers' => [
                        'content-type' => [
                            'text/html',
                        ],
                        'content-security-policy' => [
                            $this->csp,
                        ],
                        'x-content-type-options' => [
                            'nosniff',
                        ],
                        'referrer-policy' => [
                            'strict-origin',
                        ],
                        'permissions-policy' => [
                            'geolocation=()',
                        ],
                    ],
                    'body' => '<html></html>',
                    'error' => null,
                ];
            }
        };

        $destinationResolver =
            new class implements DnsResolver
            {
                public function resolve(
                    string $hostname
                ): array {
                    return ['8.8.8.8'];
                }
            };

        $recordResolver =
            new class implements DnsRecordResolver
            {
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
            $destinationResolver,
            new DnsIntelligenceEngine(
                $recordResolver
            )
        );

        return $engine->scan([
            'contract_version' => 1,
            'assessment_id' =>
                'assessment-csp-quality-v1',
            'target' => [
                'id' => 'target-csp-quality-v1',
                'url' => 'http://example.test/',
                'hostname' => 'example.test',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);
    }

    private function cspFindings(array $result): array
    {
        return array_values(
            array_filter(
                $result['findings'],
                static fn (array $finding): bool =>
                    ($finding['type'] ?? null)
                    === 'content_security_policy'
            )
        );
    }

    private function titles(array $result): array
    {
        return array_column(
            $this->cspFindings($result),
            'title'
        );
    }

    public function test_default_wildcard_reports_default_and_effective_script_risk(): void
    {
        $result = $this->scanWithCsp(
            "default-src *"
        );

        $titles = $this->titles($result);

        $this->assertContains(
            'Content-Security-Policy default-src allows wildcard sources',
            $titles
        );

        $this->assertContains(
            'Content-Security-Policy permits wildcard script sources',
            $titles
        );

        $this->assertCount(2, $titles);
    }

    public function test_explicit_script_wildcard_is_reported(): void
    {
        $result = $this->scanWithCsp(
            "default-src 'self'; script-src *"
        );

        $findings = $this->cspFindings($result);

        $this->assertCount(1, $findings);

        $this->assertSame(
            'Content-Security-Policy permits wildcard script sources',
            $findings[0]['title']
        );

        $this->assertSame(
            'high',
            $findings[0]['severity']
        );

        $this->assertSame(
            'script-src',
            $findings[0]['evidence_data']
                ['directive']
        );
    }

    public function test_unsafe_inline_script_policy_is_reported(): void
    {
        $result = $this->scanWithCsp(
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline'"
        );

        $findings = $this->cspFindings($result);

        $this->assertCount(1, $findings);

        $this->assertSame(
            'Content-Security-Policy permits unsafe inline scripts',
            $findings[0]['title']
        );

        $this->assertSame(
            "'unsafe-inline'",
            $findings[0]['evidence_data']['token']
        );
    }

    public function test_unsafe_eval_script_policy_is_reported(): void
    {
        $result = $this->scanWithCsp(
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-eval'"
        );

        $findings = $this->cspFindings($result);

        $this->assertCount(1, $findings);

        $this->assertSame(
            'Content-Security-Policy permits unsafe script evaluation',
            $findings[0]['title']
        );

        $this->assertSame(
            "'unsafe-eval'",
            $findings[0]['evidence_data']['token']
        );
    }

    public function test_restrictive_policy_creates_no_csp_quality_finding(): void
    {
        $result = $this->scanWithCsp(
            "default-src 'self'; " .
            "script-src 'self'; " .
            "object-src 'none'; " .
            "base-uri 'self'"
        );

        $this->assertSame(
            [],
            $this->cspFindings($result)
        );
    }
}
