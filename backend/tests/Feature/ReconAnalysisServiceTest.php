<?php

namespace Tests\Feature;

use App\Models\Target;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\RdapClient;
use App\Services\Scanner\ReconAnalysisService;
use App\Services\Scanner\ReconHttpTransport;
use RuntimeException;
use Tests\TestCase;

final class ReconAnalysisServiceTest extends TestCase
{
    private function target(): Target
    {
        $target = new Target();

        $target->id =
            '11111111-1111-4111-8111-111111111111';

        $target->hostname = 'example.com';
        $target->url = 'https://example.com/';
        $target->scheme = 'https';
        $target->port = 443;

        return $target;
    }

    public function test_http_recon_uses_only_validated_public_dns_answers(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return [
                    '93.184.216.34',
                    '2606:2800:220:1:248:1893:25c8:1946',
                ];
            }
        };

        $records = new class implements DnsRecordResolver {
            public function query(
                string $hostname,
                int $type
            ): array {
                return [];
            }
        };

        $transport = new class implements ReconHttpTransport {
            public array $call = [];

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
                $this->call = [
                    'url' => $url,
                    'host' => $host,
                    'port' => $port,
                    'ips' => $resolvedIps,
                    'connect_timeout' =>
                        $connectTimeoutSeconds,
                    'request_timeout' =>
                        $requestTimeoutSeconds,
                    'max_bytes' =>
                        $maxResponseBytes,
                    'headers' =>
                        $requestHeaders,
                ];

                return [
                    'ok' => true,
                    'status' => 200,
                    'successful' => true,
                    'headers' => [
                        'Server' => ['nginx'],
                        'Content-Type' => ['text/html'],
                    ],
                    'body' =>
                        '<html><title>Example</title></html>',
                    'body_bytes' => 35,
                    'duration_ms' => 1,
                    'error' => null,
                ];
            }
        };

        $rdap = new class implements RdapClient {
            public function lookupDomain(
                string $hostname
            ): array {
                return [];
            }
        };

        $service = new ReconAnalysisService(
            $dns,
            $records,
            $transport,
            $rdap
        );

        $result = $service->analyze(
            $this->target(),
            'technology-detection'
        );

        $this->assertSame(
            'https://example.com/',
            $transport->call['url']
        );

        $this->assertSame(
            'example.com',
            $transport->call['host']
        );

        $this->assertSame(
            443,
            $transport->call['port']
        );

        $this->assertSame(
            [
                '93.184.216.34',
                '2606:2800:220:1:248:1893:25c8:1946',
            ],
            $transport->call['ips']
        );

        $this->assertSame(
            5,
            $transport->call['connect_timeout']
        );

        $this->assertSame(
            10,
            $transport->call['request_timeout']
        );

        $this->assertSame(
            262144,
            $transport->call['max_bytes']
        );

        $this->assertSame(
            'example.com',
            $result['hostname']
        );

        $this->assertSame(
            200,
            $result['http_status']
        );
    }

    public function test_mixed_public_private_dns_answer_never_reaches_http_transport(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return [
                    '93.184.216.34',
                    '127.0.0.1',
                ];
            }
        };

        $records = new class implements DnsRecordResolver {
            public function query(
                string $hostname,
                int $type
            ): array {
                return [];
            }
        };

        $transport = new class implements ReconHttpTransport {
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

                return [];
            }
        };

        $rdap = new class implements RdapClient {
            public function lookupDomain(
                string $hostname
            ): array {
                return [];
            }
        };

        $service = new ReconAnalysisService(
            $dns,
            $records,
            $transport,
            $rdap
        );

        try {
            $service->analyze(
                $this->target(),
                'metadata-inspector'
            );

            $this->fail(
                'Mixed public/private DNS answer was accepted.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                0,
                $transport->calls
            );

            $this->assertStringContainsString(
                'Private or reserved',
                $exception->getMessage()
            );
        }
    }

    public function test_empty_dns_answer_never_reaches_http_transport(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return [];
            }
        };

        $records = new class implements DnsRecordResolver {
            public function query(
                string $hostname,
                int $type
            ): array {
                return [];
            }
        };

        $transport = new class implements ReconHttpTransport {
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

                return [];
            }
        };

        $rdap = new class implements RdapClient {
            public function lookupDomain(
                string $hostname
            ): array {
                return [];
            }
        };

        $service = new ReconAnalysisService(
            $dns,
            $records,
            $transport,
            $rdap
        );

        try {
            $service->analyze(
                $this->target(),
                'technology-detection'
            );

            $this->fail(
                'Empty DNS result was accepted.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                0,
                $transport->calls
            );

            $this->assertStringContainsString(
                'could not be resolved',
                $exception->getMessage()
            );
        }
    }

    public function test_asset_discovery_uses_dns_record_abstraction(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return [];
            }
        };

        $records = new class implements DnsRecordResolver {
            public array $queries = [];

            public function query(
                string $hostname,
                int $type
            ): array {
                $this->queries[] = $hostname;

                if ($hostname === 'www.example.com') {
                    return [
                        [
                            'host' => $hostname,
                            'class' => 'IN',
                            'ttl' => 300,
                            'type' => 'A',
                            'ip' => '93.184.216.34',
                        ],
                    ];
                }

                return [];
            }
        };

        $transport = new class implements ReconHttpTransport {
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
                throw new RuntimeException(
                    'HTTP transport must not run for asset discovery.'
                );
            }
        };

        $rdap = new class implements RdapClient {
            public function lookupDomain(
                string $hostname
            ): array {
                throw new RuntimeException(
                    'RDAP client must not run for asset discovery.'
                );
            }
        };

        $service = new ReconAnalysisService(
            $dns,
            $records,
            $transport,
            $rdap
        );

        $result = $service->analyze(
            $this->target(),
            'asset-discovery'
        );

        $this->assertSame(
            10,
            $result['tested_names']
        );

        $this->assertSame(
            1,
            $result['asset_count']
        );

        $this->assertSame(
            'www.example.com',
            $result['assets'][0]['hostname']
        );

        $this->assertSame(
            ['93.184.216.34'],
            $result['assets'][0]['addresses']
        );

        /*
         * Two bounded wildcard probes + ten fixed candidate names.
         */
        $this->assertCount(
            12,
            $records->queries
        );
    }

    public function test_rdap_mode_uses_dedicated_client_without_target_http_transport(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                throw new RuntimeException(
                    'Target DNS resolver must not run for RDAP mode.'
                );
            }
        };

        $records = new class implements DnsRecordResolver {
            public function query(
                string $hostname,
                int $type
            ): array {
                throw new RuntimeException(
                    'Record resolver must not run for RDAP mode.'
                );
            }
        };

        $transport = new class implements ReconHttpTransport {
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
                throw new RuntimeException(
                    'Target HTTP transport must not run for RDAP mode.'
                );
            }
        };

        $rdap = new class implements RdapClient {
            public array $lookups = [];

            public function lookupDomain(
                string $hostname
            ): array {
                $this->lookups[] = $hostname;

                return [
                    'handle' => 'EXAMPLE',
                    'ldhName' => 'EXAMPLE.COM',
                    'status' => ['active'],
                    'events' => [
                        [
                            'eventAction' =>
                                'registration',
                            'eventDate' =>
                                '1995-08-14T04:00:00Z',
                        ],
                    ],
                    'nameservers' => [
                        [
                            'ldhName' =>
                                'A.IANA-SERVERS.NET',
                        ],
                    ],
                ];
            }
        };

        $service = new ReconAnalysisService(
            $dns,
            $records,
            $transport,
            $rdap
        );

        $result = $service->analyze(
            $this->target(),
            'whois'
        );

        $this->assertSame(
            ['example.com'],
            $rdap->lookups
        );

        $this->assertSame(
            'EXAMPLE',
            $result['handle']
        );

        $this->assertSame(
            ['a.iana-servers.net'],
            $result['nameservers']
        );
    }

    public function test_target_url_authority_mismatch_is_rejected_before_network_io(): void
    {
        $dns = new class implements DnsResolver {
            public int $calls = 0;

            public function resolve(string $hostname): array
            {
                $this->calls++;

                return ['93.184.216.34'];
            }
        };

        $records = new class implements DnsRecordResolver {
            public function query(
                string $hostname,
                int $type
            ): array {
                return [];
            }
        };

        $transport = new class implements ReconHttpTransport {
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

                return [];
            }
        };

        $rdap = new class implements RdapClient {
            public function lookupDomain(
                string $hostname
            ): array {
                return [];
            }
        };

        $target = $this->target();
        $target->url = 'https://attacker.example/';

        $service = new ReconAnalysisService(
            $dns,
            $records,
            $transport,
            $rdap
        );

        try {
            $service->analyze(
                $target,
                'technology-detection'
            );

            $this->fail(
                'Mismatched URL authority was accepted.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                0,
                $dns->calls
            );

            $this->assertSame(
                0,
                $transport->calls
            );

            $this->assertStringContainsString(
                'authority',
                strtolower(
                    $exception->getMessage()
                )
            );
        }
    }
}
