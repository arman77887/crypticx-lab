<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use Tests\TestCase;

class HttpScannerDnsIntelligenceIntegrationTest extends TestCase
{
    public function test_authorized_http_scan_contains_dns_intelligence(): void
    {
        $transport = new class implements HttpTransport {
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
                        'content-type' => ['text/html'],
                    ],
                    'body' => '<html></html>',
                    'error' => null,
                ];
            }
        };

        $destinationResolver = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return ['8.8.8.8'];
            }
        };

        $recordResolver = new class implements DnsRecordResolver {
            public function query(string $hostname, int $type): array
            {
                if (
                    $hostname === 'example.test' &&
                    $type === DNS_A
                ) {
                    return [[
                        'host' => 'example.test',
                        'type' => 'A',
                        'ttl' => 300,
                        'ip' => '8.8.8.8',
                    ]];
                }

                if (
                    $hostname === 'example.test' &&
                    $type === DNS_NS
                ) {
                    return [
                        [
                            'host' => 'example.test',
                            'type' => 'NS',
                            'ttl' => 300,
                            'target' => 'ns1.example.test',
                        ],
                        [
                            'host' => 'example.test',
                            'type' => 'NS',
                            'ttl' => 300,
                            'target' => 'ns2.example.test',
                        ],
                    ];
                }

                return [];
            }
        };

        $engine = new HttpScannerEngine(
            $transport,
            $destinationResolver,
            new DnsIntelligenceEngine($recordResolver)
        );

        $result = $engine->scan([
            'contract_version' => 1,
            'assessment_id' => 'assessment-dns-v1',
            'target' => [
                'id' => 'target-dns-v1',
                'url' => 'http://example.test/',
                'hostname' => 'example.test',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        $this->assertArrayHasKey(
            'dns_intelligence',
            $result
        );

        $dns = $result['dns_intelligence'];

        $this->assertSame(
            'dns-intelligence-v1',
            $dns['engine_version']
        );

        $this->assertSame(
            'example.test',
            $dns['hostname']
        );

        $this->assertSame(
            1,
            $dns['summary']['a']
        );

        $this->assertSame(
            2,
            $dns['summary']['ns']
        );

        $this->assertTrue(
            $dns['security']['multiple_nameservers']
        );

        $this->assertSame(
            ['8.8.8.8'],
            $result['resolved_ips']
        );

        $this->assertSame(
            count($result['findings']),
            $result['finding_count']
        );
    }
}
