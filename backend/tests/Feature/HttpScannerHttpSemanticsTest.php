<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use PHPUnit\Framework\TestCase;

class HttpScannerHttpSemanticsTest extends TestCase
{
    private function scan(array $extraHeaders = []): array
    {
        $headers = array_merge(
            [
                'content-security-policy' => [
                    "default-src 'self'",
                ],
                'x-content-type-options' => [
                    'nosniff',
                ],
                'referrer-policy' => [
                    'strict-origin-when-cross-origin',
                ],
            ],
            $extraHeaders
        );

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

        return $engine->scan([
            'contract_version' => 1,
            'assessment_id' => 'assessment-http-semantics-v1',
            'target' => [
                'id' => 'target-http-semantics-v1',
                'url' => 'http://example.test/',
                'hostname' => 'example.test',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);
    }

    private function findingsOfType(
        array $result,
        string $type
    ): array {
        return array_values(
            array_filter(
                $result['findings'] ?? [],
                static fn (array $finding): bool =>
                    ($finding['type'] ?? null) === $type
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

    public function test_trace_in_allow_is_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'allow' => [
                    'GET, HEAD, TRACE',
                ],
            ]),
            'http_methods'
        );

        $this->assertCount(1, $findings);

        $this->assertSame(
            'Potentially risky HTTP methods advertised',
            $findings[0]['title']
        );

        $this->assertSame(
            ['TRACE'],
            $findings[0]['evidence_data']['methods']
        );
    }

    public function test_connect_in_allow_is_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'allow' => [
                    'GET, CONNECT',
                ],
            ]),
            'http_methods'
        );

        $this->assertCount(1, $findings);

        $this->assertSame(
            ['CONNECT'],
            $findings[0]['evidence_data']['methods']
        );
    }

    public function test_trace_and_connect_are_both_reported_in_one_finding(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'allow' => [
                    'GET, TRACE, CONNECT',
                ],
            ]),
            'http_methods'
        );

        $this->assertCount(1, $findings);

        $this->assertSame(
            ['TRACE', 'CONNECT'],
            $findings[0]['evidence_data']['methods']
        );
    }

    public function test_safe_allow_methods_do_not_create_finding(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'allow' => [
                    'GET, HEAD, POST, OPTIONS',
                ],
            ]),
            'http_methods'
        );

        $this->assertCount(0, $findings);
    }

    public function test_x_powered_by_is_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'x-powered-by' => [
                    'Express',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertContains(
            'Framework information disclosure',
            $this->titles($findings)
        );
    }

    public function test_generic_nginx_is_not_detailed_disclosure(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'server' => [
                    'nginx',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertNotContains(
            'Detailed server version disclosure',
            $this->titles($findings)
        );
    }

    public function test_generic_apache_is_not_detailed_disclosure(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'server' => [
                    'Apache',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertNotContains(
            'Detailed server version disclosure',
            $this->titles($findings)
        );
    }

    public function test_nginx_version_is_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'server' => [
                    'nginx/1.26.2',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertContains(
            'Detailed server version disclosure',
            $this->titles($findings)
        );
    }

    public function test_apache_version_is_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'server' => [
                    'Apache/2.4.62',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertContains(
            'Detailed server version disclosure',
            $this->titles($findings)
        );
    }

    public function test_microsoft_iis_version_is_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'server' => [
                    'Microsoft-IIS/10.0',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertContains(
            'Detailed server version disclosure',
            $this->titles($findings)
        );
    }

    public function test_tomcat_space_version_is_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'server' => [
                    'Tomcat 10.1.28',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertContains(
            'Detailed server version disclosure',
            $this->titles($findings)
        );
    }

    public function test_unrelated_server_header_is_not_reported(): void
    {
        $findings = $this->findingsOfType(
            $this->scan([
                'server' => [
                    'cloudflare',
                ],
            ]),
            'information_disclosure'
        );

        $this->assertCount(0, $findings);
    }
}
