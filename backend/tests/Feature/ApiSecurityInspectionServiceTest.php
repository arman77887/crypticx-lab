<?php

namespace Tests\Feature;

use App\Models\Target;
use App\Services\Scanner\ApiSecurityInspectionService;
use App\Services\Scanner\ApiSecurityIntelligenceEngine;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpTransport;
use RuntimeException;
use Tests\TestCase;

class ApiSecurityInspectionServiceTest extends TestCase
{
    private function target(
        array $overrides = []
    ): Target {
        $target = new Target();

        $target->forceFill(array_merge([
            'id' =>
                '22222222-2222-4222-8222-222222222222',
            'user_id' =>
                '11111111-1111-4111-8111-111111111111',
            'url' =>
                'https://api.example.test/v1/status',
            'hostname' => 'api.example.test',
            'scheme' => 'https',
            'port' => 443,
            'authorization_confirmed' => true,
            'status' => 'active',
        ], $overrides));

        return $target;
    }

    private function service(
        array $resolvedIps = ['93.184.216.34'],
        ?array $response = null,
        ?object &$capture = null
    ): ApiSecurityInspectionService {
        $capture = new class {
            public array $calls = [];
        };

        $transport = new class(
            $capture,
            $response
        ) implements HttpTransport {
            public function __construct(
                private object $capture,
                private ?array $response
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
                $this->capture->calls[] = [
                    'url' => $url,
                    'host' => $host,
                    'port' => $port,
                    'resolved_ips' => $resolvedIps,
                    'connect_timeout' =>
                        $connectTimeoutSeconds,
                    'request_timeout' =>
                        $requestTimeoutSeconds,
                    'max_response_bytes' =>
                        $maxResponseBytes,
                    'request_headers' =>
                        $requestHeaders,
                ];

                return $this->response ?? [
                    'ok' => true,
                    'status' => 200,
                    'successful' => true,
                    'headers' => [
                        'Content-Type' => [
                            'application/json; charset=utf-8',
                        ],
                        'Allow' => [
                            'GET, POST, TRACE',
                        ],
                        'Access-Control-Allow-Origin' => [
                            '*',
                        ],
                        'Server' => [
                            'nginx/1.26.2',
                        ],
                        'X-Powered-By' => [
                            'Express',
                        ],
                        'Content-Length' => [
                            '123',
                        ],
                    ],
                    'duration_ms' => 17,
                    'body_bytes' => 123,
                    'error' => null,
                ];
            }
        };

        $dns = new class(
            $resolvedIps
        ) implements DnsResolver {
            public function __construct(
                private array $ips
            ) {
            }

            public function resolve(
                string $hostname
            ): array {
                return $this->ips;
            }
        };

        return new ApiSecurityInspectionService(
            $transport,
            $dns,
            new ApiSecurityIntelligenceEngine()
        );
    }

    public function test_authorized_destination_is_pinned_into_bounded_get(): void
    {
        $service = $this->service(
            capture: $capture
        );

        $result = $service->inspect(
            $this->target()
        );

        $this->assertCount(
            1,
            $capture->calls
        );

        $call = $capture->calls[0];

        $this->assertSame(
            'https://api.example.test/v1/status',
            $call['url']
        );

        $this->assertSame(
            'api.example.test',
            $call['host']
        );

        $this->assertSame(
            443,
            $call['port']
        );

        $this->assertSame(
            ['93.184.216.34'],
            $call['resolved_ips']
        );

        $this->assertSame(
            5,
            $call['connect_timeout']
        );

        $this->assertSame(
            10,
            $call['request_timeout']
        );

        $this->assertSame(
            2_097_152,
            $call['max_response_bytes']
        );

        $this->assertArrayHasKey(
            'Accept',
            $call['request_headers']
        );

        $this->assertArrayHasKey(
            'User-Agent',
            $call['request_headers']
        );

        $this->assertSame(
            (string) $this->target()->id,
            $result['target_id']
        );
    }

    public function test_intelligence_findings_are_propagated(): void
    {
        $service = $this->service();

        $result = $service->inspect(
            $this->target()
        );

        $this->assertTrue(
            $result['endpoint']['json_response']
        );

        $this->assertSame(
            ['GET', 'POST', 'TRACE'],
            $result['methods']['advertised']
        );

        $this->assertSame(
            ['TRACE'],
            $result['methods']['risky']
        );

        $this->assertNull(
            $result['methods']['options_status']
        );

        $this->assertSame(
            4,
            $result['finding_count']
        );

        $titles = array_column(
            $result['findings'],
            'title'
        );

        $this->assertContains(
            'Potentially risky HTTP methods advertised',
            $titles
        );

        $this->assertContains(
            'Wildcard CORS policy advertised',
            $titles
        );

        $this->assertContains(
            'Detailed server version disclosure',
            $titles
        );

        $this->assertContains(
            'Application framework disclosure',
            $titles
        );
    }

    public function test_mixed_public_private_dns_is_rejected_before_transport(): void
    {
        $service = $this->service(
            [
                '93.184.216.34',
                '127.0.0.1',
            ],
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target()
            );

            $this->fail(
                'Unsafe mixed DNS result was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'unsafe IP targets are not allowed',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_transport_failure_is_not_analyzed(): void
    {
        $service = $this->service(
            response: [
                'ok' => false,
                'duration_ms' => 12,
                'error' => [
                    'type' => 'connection',
                    'message' => 'fixture',
                ],
            ]
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'The API endpoint request failed.'
        );

        $service->inspect(
            $this->target()
        );
    }

    public function test_target_metadata_mismatch_is_rejected_before_dns_or_transport(): void
    {
        $service = $this->service(
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target([
                    'hostname' =>
                        'different.example.test',
                ])
            );

            $this->fail(
                'Tampered target metadata was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'hostname does not match',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_scheme_metadata_mismatch_is_rejected_before_transport(): void
    {
        $service = $this->service(
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target([
                    'scheme' => 'http',
                ])
            );

            $this->fail(
                'Scheme metadata mismatch was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'scheme does not match',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_port_metadata_mismatch_is_rejected_before_transport(): void
    {
        $service = $this->service(
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target([
                    'port' => 8443,
                ])
            );

            $this->fail(
                'Port metadata mismatch was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'port does not match',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_url_credentials_are_rejected_before_transport(): void
    {
        $service = $this->service(
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target([
                    'url' =>
                        'https://user:pass@api.example.test/v1/status',
                ])
            );

            $this->fail(
                'Credential-bearing target URL was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'containing credentials are not allowed',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_direct_metadata_ip_is_rejected_before_transport(): void
    {
        $service = $this->service(
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target([
                    'url' =>
                        'http://169.254.169.254/latest/meta-data',
                    'hostname' => '169.254.169.254',
                    'scheme' => 'http',
                    'port' => 80,
                ])
            );

            $this->fail(
                'Metadata-service IP was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'unsafe IP targets are not allowed',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_ipv6_loopback_is_rejected_before_transport(): void
    {
        $service = $this->service(
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target([
                    'url' => 'http://[::1]/',
                    'hostname' => '::1',
                    'scheme' => 'http',
                    'port' => 80,
                ])
            );

            $this->fail(
                'IPv6 loopback was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'unsafe IP targets are not allowed',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_empty_dns_resolution_is_rejected_before_transport(): void
    {
        $service = $this->service(
            [],
            capture: $capture
        );

        try {
            $service->inspect(
                $this->target()
            );

            $this->fail(
                'Empty DNS resolution was accepted.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'did not resolve to a usable public IP',
                $e->getMessage()
            );
        }

        $this->assertSame(
            [],
            $capture->calls
        );
    }

    public function test_public_dns_results_are_deduplicated(): void
    {
        $service = $this->service(
            [
                '93.184.216.34',
                '93.184.216.34',
            ],
            capture: $capture
        );

        $result = $service->inspect(
            $this->target()
        );

        $this->assertSame(
            ['93.184.216.34'],
            $result['resolved_ips']
        );

        $this->assertSame(
            ['93.184.216.34'],
            $capture->calls[0]['resolved_ips']
        );
    }

    public function test_security_header_inventory_is_informational_only(): void
    {
        $service = $this->service(
            response: [
                'ok' => true,
                'status' => 200,
                'successful' => true,
                'headers' => [
                    'Content-Type' => [
                        'application/json',
                    ],
                ],
                'duration_ms' => 5,
                'body_bytes' => 2,
                'error' => null,
            ]
        );

        $result = $service->inspect(
            $this->target()
        );

        $this->assertCount(
            5,
            $result['security_headers']
        );

        foreach ($result['security_headers'] as $header) {
            $this->assertFalse(
                $header['present']
            );
        }

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }
}
