<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use App\Services\Scanner\TlsConnectionTransport;
use App\Services\Scanner\TlsIntelligenceEngine;
use Tests\TestCase;

class HttpScannerTlsFindingsTest extends TestCase
{
    private function scanWithTlsObservation(
        array $tlsObservation
    ): array {
        $httpTransport = new class implements HttpTransport
        {
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
                        /*
                         * Keep unrelated HTTP findings small and
                         * deterministic. TLS assertions filter by type.
                         */
                        'content-type' => ['text/html'],
                        'content-security-policy' => [
                            "default-src 'self'",
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
                        'strict-transport-security' => [
                            'max-age=31536000',
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

        $tlsTransport =
            new class($tlsObservation)
                implements TlsConnectionTransport
            {
                public function __construct(
                    private readonly array $observation
                ) {}

                public function inspect(
                    string $hostname,
                    string $ip,
                    int $port
                ): array {
                    return $this->observation;
                }
            };

        $engine = new HttpScannerEngine(
            $httpTransport,
            $destinationResolver,
            new DnsIntelligenceEngine(
                $recordResolver
            ),
            new TlsIntelligenceEngine(
                $tlsTransport
            )
        );

        return $engine->scan([
            'contract_version' => 1,
            'assessment_id' =>
                'assessment-tls-findings-v1',
            'target' => [
                'id' => 'target-tls-findings-v1',
                'url' => 'https://example.test/',
                'hostname' => 'example.test',
                'scheme' => 'https',
                'port' => 443,
            ],
        ]);
    }

    private function certificateObservation(
        int $validFrom,
        int $validTo
    ): array {
        return [
            'connected' => true,
            'certificate_present' => true,

            'certificate' => [
                'subject' => [
                    'CN' => 'example.test',
                ],
                'issuer' => [
                    'CN' => 'Example Test CA',
                ],
                'validFrom_time_t' => $validFrom,
                'validTo_time_t' => $validTo,
                'serialNumberHex' => 'ABC123',
                'signatureTypeSN' => 'RSA-SHA256',
                'extensions' => [
                    'subjectAltName' =>
                        'DNS:example.test',
                ],
            ],

            'sha256_fingerprint' =>
                implode(
                    ':',
                    array_fill(0, 32, 'aa')
                ),

            'certificate_chain' => [],

            'crypto' => [
                'protocol' => 'TLSv1.3',
                'cipher_name' =>
                    'TLS_AES_256_GCM_SHA384',
                'cipher_bits' => 256,
                'cipher_version' => 'TLSv1.3',
            ],
        ];
    }

    private function tlsFindings(
        array $result
    ): array {
        return array_values(
            array_filter(
                $result['findings'],
                static fn (array $finding): bool =>
                    ($finding['type'] ?? null)
                    === 'tls_certificate'
            )
        );
    }

    public function test_expired_certificate_creates_high_finding(): void
    {
        $now = time();

        $result = $this->scanWithTlsObservation(
            $this->certificateObservation(
                $now - (90 * 86400),
                $now - (2 * 86400)
            )
        );

        $findings = $this->tlsFindings($result);

        $this->assertCount(1, $findings);

        $this->assertSame(
            'TLS certificate has expired',
            $findings[0]['title']
        );

        $this->assertSame(
            'high',
            $findings[0]['severity']
        );

        $this->assertSame(
            'high',
            $findings[0]['confidence']
        );

        $this->assertTrue(
            $findings[0]['evidence_data']
                ['expired']
        );
    }

    public function test_not_yet_valid_certificate_creates_high_finding(): void
    {
        $now = time();

        $result = $this->scanWithTlsObservation(
            $this->certificateObservation(
                $now + (2 * 86400),
                $now + (90 * 86400)
            )
        );

        $findings = $this->tlsFindings($result);

        $this->assertCount(1, $findings);

        $this->assertSame(
            'TLS certificate is not yet valid',
            $findings[0]['title']
        );

        $this->assertSame(
            'high',
            $findings[0]['severity']
        );

        $this->assertTrue(
            $findings[0]['evidence_data']
                ['not_yet_valid']
        );
    }

    public function test_certificate_expiring_within_fourteen_days_creates_low_finding(): void
    {
        $now = time();

        $result = $this->scanWithTlsObservation(
            $this->certificateObservation(
                $now - (30 * 86400),
                $now + (10 * 86400)
            )
        );

        $findings = $this->tlsFindings($result);

        $this->assertCount(1, $findings);

        $this->assertSame(
            'TLS certificate expires soon',
            $findings[0]['title']
        );

        $this->assertSame(
            'low',
            $findings[0]['severity']
        );

        $this->assertSame(
            14,
            $findings[0]['evidence_data']
                ['threshold_days']
        );
    }

    public function test_certificate_with_more_than_fourteen_days_creates_no_tls_finding(): void
    {
        $now = time();

        $result = $this->scanWithTlsObservation(
            $this->certificateObservation(
                $now - (30 * 86400),
                $now + (30 * 86400)
            )
        );

        $this->assertSame(
            [],
            $this->tlsFindings($result)
        );
    }

    public function test_connection_failure_does_not_fabricate_tls_vulnerability(): void
    {
        $result = $this->scanWithTlsObservation([
            'connected' => false,
            'certificate_present' => false,
            'error_code' => 0,
            'error_message' =>
                'TLS connection failed.',
        ]);

        $this->assertSame(
            'connection_failed',
            $result['tls']['status']
        );

        $this->assertSame(
            [],
            $this->tlsFindings($result)
        );
    }
}
