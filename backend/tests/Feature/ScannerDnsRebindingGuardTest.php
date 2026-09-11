<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use RuntimeException;
use Tests\TestCase;

class ScannerDnsRebindingGuardTest extends TestCase
{
    public function test_same_host_redirect_is_blocked_when_dns_rebinds_to_metadata_ip(): void
    {
        $resolver = new class implements DnsResolver {
            public int $calls = 0;

            public function resolve(string $hostname): array
            {
                $this->calls++;

                if ($hostname !== 'scanner-rebind.test') {
                    throw new RuntimeException(
                        'Unexpected hostname reached DNS resolver.'
                    );
                }

                return match ($this->calls) {
                    1 => ['8.8.8.8'],
                    2 => ['169.254.169.254'],
                    default => throw new RuntimeException(
                        'Unexpected additional DNS resolution.'
                    ),
                };
            }
        };

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
                    $url !== 'http://scanner-rebind.test/' ||
                    $host !== 'scanner-rebind.test' ||
                    $port !== 80 ||
                    $resolvedIps !== ['8.8.8.8']
                ) {
                    throw new RuntimeException(
                        'DNS-rebound destination reached transport boundary.'
                    );
                }

                return [
                    'ok' => true,
                    'status' => 302,
                    'successful' => false,
                    'headers' => [
                        'Location' => ['/next'],
                    ],
                    'duration_ms' => 1,
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $scanner = new HttpScannerEngine(
            $transport,
            $resolver
        );

        $result = $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'dns-rebinding-test',
            'target' => [
                'id' => 'dns-rebinding-target',
                'url' => 'http://scanner-rebind.test/',
                'hostname' => 'scanner-rebind.test',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        /*
         * Initial resolution + mandatory redirect-hop re-resolution.
         */
        $this->assertSame(2, $resolver->calls);

        /*
         * Only the original authorized public destination may reach
         * transport: primary GET + bounded CORS Origin probe.
         * The DNS-rebound metadata address must never cross the
         * transport boundary.
         */
        $this->assertSame(2, $transport->calls);
        $this->assertCount(2, $transport->requests);

        foreach ($transport->requests as $request) {
            $this->assertSame(
                'http://scanner-rebind.test/',
                $request['url']
            );

            $this->assertSame(
                'scanner-rebind.test',
                $request['host']
            );

            $this->assertSame(
                ['8.8.8.8'],
                $request['resolved_ips']
            );
        }

        $this->assertSame(
            [],
            $transport->requests[0]['request_headers']
        );

        $this->assertSame(
            [
                'Origin' =>
                    'https://cors-probe.invalid',
            ],
            $transport->requests[1]['request_headers']
        );

        $this->assertSame(
            1,
            $result['redirect_hops']
        );

        $this->assertFalse(
            $result['redirect_chain'][0]['followed']
        );

        $this->assertSame(
            'http://scanner-rebind.test/next',
            $result['redirect_chain'][0]['to']
        );

        $this->assertSame(
            'Redirect destination failed network safety validation.',
            $result['redirect_chain'][0]['reason']
        );
    }

    public function test_same_host_redirect_can_continue_when_reresolution_remains_public(): void
    {
        $resolver = new class implements DnsResolver {
            public int $calls = 0;

            public function resolve(string $hostname): array
            {
                $this->calls++;

                return match ($this->calls) {
                    1 => ['8.8.8.8'],
                    2 => ['1.1.1.1'],
                    default => throw new RuntimeException(
                        'Unexpected additional DNS resolution.'
                    ),
                };
            }
        };

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

                /*
                 * Primary request returns the same-host redirect.
                 */
                if (
                    $url === 'http://scanner-rebind.test/' &&
                    $resolvedIps === ['8.8.8.8'] &&
                    $requestHeaders === []
                ) {
                    return [
                        'ok' => true,
                        'status' => 302,
                        'successful' => false,
                        'headers' => [
                            'Location' => ['/next'],
                        ],
                        'duration_ms' => 1,
                        'body_bytes' => 0,
                        'error' => null,
                    ];
                }

                /*
                 * CORS probe remains on the original validated/pinned
                 * destination and must not be mistaken for a redirect
                 * hop.
                 */
                if (
                    $url === 'http://scanner-rebind.test/' &&
                    $resolvedIps === ['8.8.8.8'] &&
                    $requestHeaders === [
                        'Origin' =>
                            'https://cors-probe.invalid',
                    ]
                ) {
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

                /*
                 * Followed same-host redirect must use the fresh public
                 * DNS result.
                 */
                if (
                    $url === 'http://scanner-rebind.test/next' &&
                    $resolvedIps === ['1.1.1.1'] &&
                    $requestHeaders === []
                ) {
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

                throw new RuntimeException(
                    'Unexpected request crossed transport boundary.'
                );
            }
        };

        $scanner = new HttpScannerEngine(
            $transport,
            $resolver
        );

        $result = $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'dns-reresolution-public-test',
            'target' => [
                'id' => 'dns-reresolution-public-target',
                'url' => 'http://scanner-rebind.test/',
                'hostname' => 'scanner-rebind.test',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        $this->assertSame(2, $resolver->calls);
        $this->assertSame(3, $transport->calls);
        $this->assertCount(3, $transport->requests);

        $this->assertSame(
            ['8.8.8.8'],
            $transport->requests[0]['resolved_ips']
        );

        $this->assertSame(
            [],
            $transport->requests[0]['request_headers']
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

        $this->assertSame(
            'http://scanner-rebind.test/next',
            $transport->requests[2]['url']
        );

        $this->assertSame(
            ['1.1.1.1'],
            $transport->requests[2]['resolved_ips']
        );

        $this->assertSame(
            [],
            $transport->requests[2]['request_headers']
        );

        $this->assertTrue(
            $result['redirect_chain'][0]['followed']
        );

        $this->assertSame(
            ['1.1.1.1'],
            $result['redirect_chain'][0]['resolved_ips']
        );
    }
}
