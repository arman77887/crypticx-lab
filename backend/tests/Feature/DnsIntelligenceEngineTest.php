<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use Tests\TestCase;

class DnsIntelligenceEngineTest extends TestCase
{
    public function test_dns_intelligence_returns_normalized_records_and_security_summary(): void
    {
        $resolver = new class implements DnsRecordResolver {
            public array $queries = [];

            public function query(string $hostname, int $type): array
            {
                $this->queries[] = [$hostname, $type];

                if ($hostname === '_dmarc.example.test' && $type === DNS_TXT) {
                    return [[
                        'host' => '_dmarc.example.test',
                        'type' => 'TXT',
                        'ttl' => 300,
                        'txt' => 'v=DMARC1; p=reject',
                    ]];
                }

                if ($hostname !== 'example.test') {
                    return [];
                }

                return match ($type) {
                    DNS_A => [[
                        'host' => 'example.test',
                        'type' => 'A',
                        'ttl' => 300,
                        'ip' => '203.0.113.10',
                    ]],

                    DNS_AAAA => [[
                        'host' => 'example.test',
                        'type' => 'AAAA',
                        'ttl' => 300,
                        'ipv6' => '2001:db8::10',
                    ]],

                    DNS_CNAME => [],

                    DNS_MX => [[
                        'host' => 'example.test',
                        'type' => 'MX',
                        'ttl' => 300,
                        'pri' => 10,
                        'target' => 'mail.example.test',
                    ]],

                    DNS_NS => [
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
                    ],

                    DNS_TXT => [[
                        'host' => 'example.test',
                        'type' => 'TXT',
                        'ttl' => 300,
                        'txt' => 'v=spf1 -all',
                    ]],

                    DNS_CAA => [[
                        'host' => 'example.test',
                        'type' => 'CAA',
                        'ttl' => 300,
                        'value' => 'letsencrypt.org',
                        'tag' => 'issue',
                    ]],

                    DNS_SOA => [[
                        'host' => 'example.test',
                        'type' => 'SOA',
                        'ttl' => 300,
                        'mname' => 'ns1.example.test',
                        'rname' => 'hostmaster.example.test',
                        'serial' => 2026090901,
                    ]],

                    default => [],
                };
            }
        };

        $engine = new DnsIntelligenceEngine($resolver);

        $result = $engine->analyze('Example.Test.');

        $this->assertSame(
            'dns-intelligence-v1',
            $result['engine_version']
        );

        $this->assertSame(
            'example.test',
            $result['hostname']
        );

        $this->assertSame(1, $result['summary']['a']);
        $this->assertSame(1, $result['summary']['aaaa']);
        $this->assertSame(0, $result['summary']['cname']);
        $this->assertSame(1, $result['summary']['mx']);
        $this->assertSame(2, $result['summary']['ns']);
        $this->assertSame(1, $result['summary']['txt']);
        $this->assertSame(1, $result['summary']['caa']);
        $this->assertSame(1, $result['summary']['soa']);

        $this->assertTrue(
            $result['security']['spf_present']
        );

        $this->assertTrue(
            $result['security']['dmarc_present']
        );

        $this->assertTrue(
            $result['security']['caa_present']
        );

        $this->assertTrue(
            $result['security']['multiple_nameservers']
        );

        $this->assertSame(
            '203.0.113.10',
            $result['records']['A'][0]['ip']
        );

        $this->assertSame(
            'mail.example.test',
            $result['records']['MX'][0]['target']
        );

        $this->assertSame(
            'v=DMARC1; p=reject',
            $result['dmarc_records'][0]['txt']
        );

        $this->assertCount(
            9,
            $resolver->queries
        );
    }

    public function test_missing_optional_dns_policies_are_reported_without_fake_findings(): void
    {
        $resolver = new class implements DnsRecordResolver {
            public function query(string $hostname, int $type): array
            {
                if (
                    $hostname === 'example.test' &&
                    $type === DNS_A
                ) {
                    return [[
                        'host' => 'example.test',
                        'type' => 'A',
                        'ttl' => 60,
                        'ip' => '203.0.113.20',
                    ]];
                }

                return [];
            }
        };

        $result = (new DnsIntelligenceEngine(
            $resolver
        ))->analyze('example.test');

        $this->assertFalse(
            $result['security']['spf_present']
        );

        $this->assertFalse(
            $result['security']['dmarc_present']
        );

        $this->assertFalse(
            $result['security']['caa_present']
        );

        $this->assertFalse(
            $result['security']['multiple_nameservers']
        );

        $this->assertSame(
            [],
            $result['dmarc_records']
        );
    }

    public function test_invalid_hostname_is_rejected_before_dns_query(): void
    {
        $resolver = new class implements DnsRecordResolver {
            public int $calls = 0;

            public function query(string $hostname, int $type): array
            {
                $this->calls++;

                return [];
            }
        };

        $engine = new DnsIntelligenceEngine($resolver);

        try {
            $engine->analyze('https://example.test/path');

            $this->fail(
                'Invalid hostname was unexpectedly accepted.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'DNS intelligence hostname is invalid.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, $resolver->calls);
    }

    public function test_record_count_is_bounded_per_dns_type(): void
    {
        $resolver = new class implements DnsRecordResolver {
            public function query(string $hostname, int $type): array
            {
                if ($type !== DNS_A) {
                    return [];
                }

                $records = [];

                for ($i = 0; $i < 150; $i++) {
                    $records[] = [
                        'host' => $hostname,
                        'type' => 'A',
                        'ttl' => 60,
                        'ip' => '203.0.113.' . (($i % 200) + 1),
                    ];
                }

                return $records;
            }
        };

        $result = (new DnsIntelligenceEngine(
            $resolver
        ))->analyze('example.test');

        $this->assertCount(
            100,
            $result['records']['A']
        );

        $this->assertSame(
            100,
            $result['summary']['a']
        );
    }

    public function test_dns_payload_has_aggregate_size_limit(): void
    {
        $resolver = new class implements \App\Services\Scanner\DnsRecordResolver
        {
            public function query(
                string $hostname,
                int $type
            ): array {
                if ($type !== DNS_TXT) {
                    return [];
                }

                /*
                 * Each record remains individually valid:
                 * 32 entries × 4096 bytes.
                 * The engine's per-type 100-record ceiling still
                 * applies, but aggregate JSON must be rejected
                 * well before the scanner stdout boundary.
                 */
                $record = [
                    'host' => $hostname,
                    'type' => 'TXT',
                    'ttl' => 300,
                    'txt' => 'v=test',
                    'entries' => array_fill(
                        0,
                        32,
                        str_repeat('A', 4096)
                    ),
                ];

                return array_fill(
                    0,
                    100,
                    $record
                );
            }
        };

        $engine = new \App\Services\Scanner\DnsIntelligenceEngine(
            $resolver
        );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'DNS intelligence result exceeds the size limit.'
        );

        $engine->analyze('example.test');
    }

}

