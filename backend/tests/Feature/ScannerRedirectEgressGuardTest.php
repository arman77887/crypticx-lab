<?php

namespace Tests\Feature;

use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use RuntimeException;
use Tests\TestCase;

class ScannerRedirectEgressGuardTest extends TestCase
{
    public function test_cross_host_redirect_to_metadata_ip_is_never_contacted(): void
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
                        'Cross-host redirect reached transport boundary.'
                    );
                }

                return [
                    'ok' => true,
                    'status' => 302,
                    'successful' => false,
                    'headers' => [
                        'Location' => [
                            'http://169.254.169.254/latest/meta-data/',
                        ],
                    ],
                    'duration_ms' => 1,
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $scanner = new HttpScannerEngine($transport);

        $result = $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'redirect-egress-test',
            'target' => [
                'id' => 'redirect-egress-target',
                'url' => 'http://8.8.8.8/',
                'hostname' => '8.8.8.8',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        $this->assertSame(
            2,
            $transport->calls,
            'Expected only the primary request and bounded CORS probe.'
        );

        $this->assertCount(2, $transport->requests);

        $this->assertSame(
            '8.8.8.8',
            $transport->requests[0]['host']
        );

        $this->assertSame(
            'http://8.8.8.8/',
            $transport->requests[1]['url']
        );

        $this->assertSame(
            '8.8.8.8',
            $transport->requests[1]['host']
        );

        $this->assertSame(
            ['8.8.8.8'],
            $transport->requests[1]['resolved_ips']
        );

        $this->assertSame(
            [
                'Origin' =>
                    'https://cors-probe.invalid',
            ],
            $transport->requests[1]['request_headers']
        );

        $this->assertSame(1, $result['redirect_hops']);

        $this->assertFalse(
            $result['redirect_chain'][0]['followed']
        );

        $this->assertSame(
            'Redirect leaves the authorized target hostname.',
            $result['redirect_chain'][0]['reason']
        );

        $this->assertSame(
            'http://169.254.169.254/latest/meta-data/',
            $result['redirect_chain'][0]['to']
        );
    }

    public function test_cross_host_public_redirect_is_also_never_contacted(): void
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
                        'Cross-host redirect reached transport boundary.'
                    );
                }

                return [
                    'ok' => true,
                    'status' => 302,
                    'successful' => false,
                    'headers' => [
                        'Location' => [
                            'https://1.1.1.1/',
                        ],
                    ],
                    'duration_ms' => 1,
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $scanner = new HttpScannerEngine($transport);

        $result = $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'redirect-scope-test',
            'target' => [
                'id' => 'redirect-scope-target',
                'url' => 'http://8.8.8.8/',
                'hostname' => '8.8.8.8',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        $this->assertSame(2, $transport->calls);

        $this->assertCount(
            2,
            $transport->requests
        );

        $this->assertSame(
            'http://8.8.8.8/',
            $transport->requests[1]['url']
        );

        $this->assertSame(
            '8.8.8.8',
            $transport->requests[1]['host']
        );

        $this->assertSame(
            ['8.8.8.8'],
            $transport->requests[1]['resolved_ips']
        );

        $this->assertSame(
            [
                'Origin' =>
                    'https://cors-probe.invalid',
            ],
            $transport->requests[1]['request_headers']
        );

        $this->assertFalse(
            $result['redirect_chain'][0]['followed']
        );

        $this->assertSame(
            'Redirect leaves the authorized target hostname.',
            $result['redirect_chain'][0]['reason']
        );
    }

    public function test_non_http_redirect_is_never_contacted(): void
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
                        'Non-HTTP redirect reached transport boundary.'
                    );
                }

                return [
                    'ok' => true,
                    'status' => 302,
                    'successful' => false,
                    'headers' => [
                        'Location' => [
                            'file:///etc/passwd',
                        ],
                    ],
                    'duration_ms' => 1,
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $scanner = new HttpScannerEngine($transport);

        $result = $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'redirect-protocol-test',
            'target' => [
                'id' => 'redirect-protocol-target',
                'url' => 'http://8.8.8.8/',
                'hostname' => '8.8.8.8',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        $this->assertSame(2, $transport->calls);

        $this->assertCount(
            2,
            $transport->requests
        );

        $this->assertSame(
            'http://8.8.8.8/',
            $transport->requests[1]['url']
        );

        $this->assertSame(
            '8.8.8.8',
            $transport->requests[1]['host']
        );

        $this->assertSame(
            ['8.8.8.8'],
            $transport->requests[1]['resolved_ips']
        );

        $this->assertSame(
            [
                'Origin' =>
                    'https://cors-probe.invalid',
            ],
            $transport->requests[1]['request_headers']
        );

        $this->assertFalse(
            $result['redirect_chain'][0]['followed']
        );

        $this->assertSame(
            'Redirect uses a non-HTTP(S) scheme.',
            $result['redirect_chain'][0]['reason']
        );
    }
}
