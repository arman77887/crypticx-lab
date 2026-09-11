<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DnsIntelligenceToolContractTest extends TestCase
{
    private function authenticate(): void
    {
        $user = new User();
        $user->id = '00000000-0000-4000-8000-000000000123';

        Sanctum::actingAs($user);
    }

    private function bindDeterministicDns(): void
    {
        $resolver = new class implements DnsRecordResolver
        {
            public function query(
                string $hostname,
                int $type
            ): array {
                if (
                    $hostname === '_dmarc.example.test' &&
                    $type === DNS_TXT
                ) {
                    return [[
                        'host' => $hostname,
                        'type' => 'TXT',
                        'ttl' => 300,
                        'txt' => 'v=DMARC1; p=reject',
                    ]];
                }

                return match ($type) {
                    DNS_A => [[
                        'host' => $hostname,
                        'type' => 'A',
                        'ttl' => 300,
                        'ip' => '8.8.8.8',
                    ]],

                    DNS_NS => [
                        [
                            'host' => $hostname,
                            'type' => 'NS',
                            'ttl' => 300,
                            'target' => 'ns1.example.test',
                        ],
                        [
                            'host' => $hostname,
                            'type' => 'NS',
                            'ttl' => 300,
                            'target' => 'ns2.example.test',
                        ],
                    ],

                    DNS_TXT => [[
                        'host' => $hostname,
                        'type' => 'TXT',
                        'ttl' => 300,
                        'txt' => 'v=spf1 -all',
                    ]],

                    DNS_CAA => [[
                        'host' => $hostname,
                        'type' => 'CAA',
                        'ttl' => 300,
                        'tag' => 'issue',
                        'value' => 'letsencrypt.org',
                    ]],

                    DNS_SOA => [[
                        'host' => $hostname,
                        'type' => 'SOA',
                        'ttl' => 300,
                        'mname' => 'ns1.example.test',
                        'rname' => 'hostmaster.example.test',
                        'serial' => 1,
                    ]],

                    default => [],
                };
            }
        };

        $this->app->instance(
            DnsRecordResolver::class,
            $resolver
        );

        $this->app->bind(
            DnsIntelligenceEngine::class,
            fn ($app) => new DnsIntelligenceEngine(
                $app->make(DnsRecordResolver::class)
            )
        );
    }

    public function test_record_inspector_keeps_live_api_contract(): void
    {
        $this->authenticate();
        $this->bindDeterministicDns();

        $response = $this->postJson(
            '/api/v1/tools/dns-intelligence',
            [
                'hostname' => 'example.test',
                'tool' => 'record-inspector',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.hostname',
                'example.test'
            )
            ->assertJsonPath(
                'data.record_count',
                6
            )
            ->assertJsonPath(
                'data.records.A.0.ip',
                '8.8.8.8'
            )
            ->assertJsonPath(
                'data.records.NS.1.target',
                'ns2.example.test'
            )
            ->assertJsonStructure([
                'success',
                'data' => [
                    'hostname',
                    'records',
                    'record_count',
                    'types',
                    'duration_ms',
                    'checked_at',
                ],
            ]);
    }

    public function test_dns_health_keeps_live_api_contract(): void
    {
        $this->authenticate();
        $this->bindDeterministicDns();

        $response = $this->postJson(
            '/api/v1/tools/dns-intelligence',
            [
                'hostname' => 'example.test',
                'tool' => 'dns-health',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.hostname',
                'example.test'
            )
            ->assertJsonPath(
                'data.summary.a',
                1
            )
            ->assertJsonPath(
                'data.summary.ns',
                2
            )
            ->assertJsonPath(
                'data.summary.spf_present',
                true
            )
            ->assertJsonPath(
                'data.summary.dmarc_present',
                true
            )
            ->assertJsonPath(
                'data.checks.0.status',
                'pass'
            )
            ->assertJsonPath(
                'data.checks.1.status',
                'pass'
            )
            ->assertJsonPath(
                'data.checks.2.status',
                'pass'
            )
            ->assertJsonPath(
                'data.checks.3.status',
                'pass'
            )
            ->assertJsonPath(
                'data.checks.4.status',
                'pass'
            )
            ->assertJsonPath(
                'data.checks.5.status',
                'pass'
            )
            ->assertJsonStructure([
                'success',
                'data' => [
                    'hostname',
                    'summary' => [
                        'a',
                        'aaaa',
                        'ns',
                        'mx',
                        'txt',
                        'caa',
                        'soa',
                        'spf_present',
                        'dmarc_present',
                    ],
                    'checks',
                    'duration_ms',
                    'checked_at',
                ],
            ]);
    }
}
