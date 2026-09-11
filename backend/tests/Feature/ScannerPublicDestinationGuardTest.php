<?php

namespace Tests\Feature;

use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use RuntimeException;
use Tests\TestCase;

class ScannerPublicDestinationGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function unsafeIpProvider(): array
    {
        return [
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv4 private 10/8' => ['10.0.0.1'],
            'IPv4 private 172.16/12' => ['172.16.0.1'],
            'IPv4 private 192.168/16' => ['192.168.0.1'],
            'IPv4 link-local metadata' => ['169.254.169.254'],
            'IPv4 unspecified' => ['0.0.0.0'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unique-local' => ['fc00::1'],
            'IPv6 link-local' => ['fe80::1'],
        ];
    }

    /**
     * @dataProvider unsafeIpProvider
     */
    public function test_unsafe_direct_ip_is_rejected_before_network_io(
        string $ip
    ): void {
        $transport = new class implements HttpTransport {
            public int $calls = 0;

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
                $this->calls++;

                throw new RuntimeException(
                    'Unsafe destination reached transport boundary.'
                );
            }
        };

        $scanner = new HttpScannerEngine($transport);

        try {
            $scanner->scan(
                $this->requestForIp($ip)
            );

            $this->fail(
                "Unsafe destination {$ip} was accepted."
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Private, reserved, loopback, link-local, or otherwise unsafe IP targets are not allowed.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            0,
            $transport->calls,
            "Transport was called for unsafe destination {$ip}."
        );
    }

    public function test_public_ipv4_reaches_transport_with_pinned_ip(): void
    {
        $transport = new class implements HttpTransport {
            public int $calls = 0;

            public array $requests = [];

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
                $this->calls++;

                $this->requests[] = [
                    'url' => $url,
                    'host' => $host,
                    'port' => $port,
                    'resolved_ips' => $resolvedIps,
                    'request_headers' => $requestHeaders,
                ];

                if (
                    $url !== 'http://8.8.8.8/' ||
                    $host !== '8.8.8.8' ||
                    $port !== 80 ||
                    $resolvedIps !== ['8.8.8.8']
                ) {
                    throw new RuntimeException(
                        'Unexpected destination reached transport boundary.'
                    );
                }

                return [
                    'ok' => true,
                    'status' => 200,
                    'successful' => true,
                    'headers' => [],
                    'duration_ms' => 1,
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $scanner = new HttpScannerEngine($transport);

        $result = $scanner->scan(
            $this->requestForIp('8.8.8.8')
        );

        $this->assertSame(
            2,
            $transport->calls,
            'Expected primary request plus one bounded CORS probe.'
        );

        $this->assertCount(
            2,
            $transport->requests
        );

        $this->assertSame(
            [
                'url' => 'http://8.8.8.8/',
                'host' => '8.8.8.8',
                'port' => 80,
                'resolved_ips' => ['8.8.8.8'],
                'request_headers' => [],
            ],
            $transport->requests[0]
        );

        $this->assertSame(
            [
                'url' => 'http://8.8.8.8/',
                'host' => '8.8.8.8',
                'port' => 80,
                'resolved_ips' => ['8.8.8.8'],
                'request_headers' => [
                    'Origin' =>
                        'https://cors-probe.invalid',
                ],
            ],
            $transport->requests[1]
        );

        $this->assertSame(
            ['8.8.8.8'],
            $result['resolved_ips']
        );

        $this->assertTrue(
            $result['scanner_policy']['dns_pinning']
        );
    }

    private function requestForIp(string $ip): array
    {
        /*
         * RFC 3986 requires brackets around an IPv6 literal
         * when it appears in a URL authority.
         */
        $urlHost = str_contains($ip, ':')
            ? "[{$ip}]"
            : $ip;

        return [
            'contract_version' => 1,
            'assessment_id' => 'destination-guard-test',
            'target' => [
                'id' => 'destination-guard-target',
                'url' => "http://{$urlHost}/",
                'hostname' => $ip,
                'scheme' => 'http',
                'port' => 80,
            ],
        ];
    }
}
