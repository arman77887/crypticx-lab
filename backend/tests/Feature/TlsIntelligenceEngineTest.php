<?php

namespace Tests\Feature;

use App\Services\Scanner\TlsConnectionTransport;
use App\Services\Scanner\TlsIntelligenceEngine;
use Tests\TestCase;

class TlsIntelligenceEngineTest extends TestCase
{
    public function test_no_resolved_ip_is_not_observed(): void
    {
        $transport = new FakeTlsConnectionTransport([
            'connected' => true,
        ]);

        $result = (new TlsIntelligenceEngine($transport))
            ->inspect('example.test', 443, []);

        $this->assertFalse($result['enabled']);
        $this->assertSame('not_observed', $result['status']);
        $this->assertNull($result['certificate']);
        $this->assertNull($result['connection']);

        $this->assertSame(0, $transport->calls);
    }

    public function test_connection_failure_preserves_structured_contract(): void
    {
        $transport = new FakeTlsConnectionTransport([
            'connected' => false,
            'error_code' => 111,
        ]);

        $result = (new TlsIntelligenceEngine($transport))
            ->inspect(
                'example.test',
                443,
                ['203.0.113.10']
            );

        $this->assertTrue($result['enabled']);
        $this->assertSame(
            'connection_failed',
            $result['status']
        );

        $this->assertSame(
            [
                'ip' => '203.0.113.10',
                'port' => 443,
            ],
            $result['connection']
        );

        $this->assertSame(
            'tls_connection_failed',
            $result['error']['type']
        );

        $this->assertSame(
            111,
            $result['error']['code']
        );

        $this->assertSame(
            [
                'hostname' => 'example.test',
                'ip' => '203.0.113.10',
                'port' => 443,
            ],
            $transport->lastCall
        );
    }

    public function test_missing_certificate_is_reported(): void
    {
        $transport = new FakeTlsConnectionTransport([
            'connected' => true,
            'certificate_present' => false,
            'crypto' => [
                'protocol' => 'TLSv1.3',
                'cipher_name' =>
                    'TLS_AES_256_GCM_SHA384',
                'cipher_bits' => 256,
                'cipher_version' => 'TLSv1.3',
            ],
        ]);

        $result = (new TlsIntelligenceEngine($transport))
            ->inspect(
                'example.test',
                443,
                ['203.0.113.10']
            );

        $this->assertSame(
            'certificate_unavailable',
            $result['status']
        );

        $this->assertNull($result['certificate']);

        $this->assertSame(
            'TLSv1.3',
            $result['connection']['protocol']
        );

        $this->assertSame(
            'certificate_not_captured',
            $result['error']['type']
        );
    }

    public function test_unparseable_certificate_is_reported(): void
    {
        $transport = new FakeTlsConnectionTransport([
            'connected' => true,
            'certificate_present' => true,
            'certificate' => null,
            'crypto' => [],
        ]);

        $result = (new TlsIntelligenceEngine($transport))
            ->inspect(
                'example.test',
                443,
                ['203.0.113.10']
            );

        $this->assertSame(
            'certificate_parse_failed',
            $result['status']
        );

        $this->assertNull($result['certificate']);

        $this->assertSame(
            'certificate_parse_failed',
            $result['error']['type']
        );
    }

    public function test_verified_certificate_is_normalized(): void
    {
        $now = time();

        $transport = new FakeTlsConnectionTransport([
            'connected' => true,
            'certificate_present' => true,
            'certificate' => [
                'subject' => [
                    'CN' => 'example.test',
                    'O' => 'CrypticX Test',
                ],
                'issuer' => [
                    'CN' => 'Example Test CA',
                ],
                'validFrom_time_t' =>
                    $now - 86400,
                'validTo_time_t' =>
                    $now + (30 * 86400),
                'serialNumberHex' => 'A1B2C3',
                'signatureTypeSN' =>
                    'RSA-SHA256',
                'extensions' => [
                    'subjectAltName' =>
                        'DNS:example.test, ' .
                        'DNS:www.example.test, ' .
                        'IP Address:203.0.113.10',
                ],
            ],
            'sha256_fingerprint' =>
                'aa:bb:cc:dd',
            'crypto' => [
                'protocol' => 'TLSv1.3',
                'cipher_name' =>
                    'TLS_AES_256_GCM_SHA384',
                'cipher_bits' => 256,
                'cipher_version' => 'TLSv1.3',
            ],
        ]);

        $result = (new TlsIntelligenceEngine($transport))
            ->inspect(
                'example.test',
                443,
                ['203.0.113.10']
            );

        $this->assertSame(
            'verified',
            $result['status']
        );

        $this->assertSame(
            'TLSv1.3',
            $result['connection']['protocol']
        );

        $this->assertSame(
            'TLS_AES_256_GCM_SHA384',
            $result['connection']['cipher_name']
        );

        $this->assertSame(
            256,
            $result['connection']['cipher_bits']
        );

        $certificate = $result['certificate'];

        $this->assertSame(
            'example.test',
            $certificate['subject']
        );

        $this->assertSame(
            'Example Test CA',
            $certificate['issuer']
        );

        $this->assertSame(
            'A1B2C3',
            $certificate['serial']
        );

        $this->assertSame(
            'RSA-SHA256',
            $certificate['signature_algorithm']
        );

        $this->assertSame(
            'aa:bb:cc:dd',
            $certificate['sha256_fingerprint']
        );

        $this->assertFalse(
            $certificate['expired']
        );

        $this->assertFalse(
            $certificate['not_yet_valid']
        );

        /*
         * time() may cross a second boundary during the test,
         * therefore assert the safe day range instead of an
         * unnecessarily brittle exact value.
         */
        $this->assertGreaterThanOrEqual(
            29,
            $certificate['days_remaining']
        );

        $this->assertLessThanOrEqual(
            30,
            $certificate['days_remaining']
        );

        $this->assertSame(
            [
                [
                    'type' => 'dns',
                    'value' => 'example.test',
                ],
                [
                    'type' => 'dns',
                    'value' => 'www.example.test',
                ],
                [
                    'type' => 'ip',
                    'value' => '203.0.113.10',
                ],
            ],
            $certificate['subject_alt_names']
        );
    }

    public function test_expired_and_not_yet_valid_states_are_derived(): void
    {
        $now = time();

        $expired = new FakeTlsConnectionTransport([
            'connected' => true,
            'certificate_present' => true,
            'certificate' => [
                'validFrom_time_t' =>
                    $now - (10 * 86400),
                'validTo_time_t' =>
                    $now - 86400,
            ],
            'crypto' => [],
        ]);

        $expiredResult =
            (new TlsIntelligenceEngine($expired))
                ->inspect(
                    'example.test',
                    443,
                    ['203.0.113.10']
                );

        $this->assertTrue(
            $expiredResult['certificate']['expired']
        );

        $future = new FakeTlsConnectionTransport([
            'connected' => true,
            'certificate_present' => true,
            'certificate' => [
                'validFrom_time_t' =>
                    $now + 86400,
                'validTo_time_t' =>
                    $now + (30 * 86400),
            ],
            'crypto' => [],
        ]);

        $futureResult =
            (new TlsIntelligenceEngine($future))
                ->inspect(
                    'example.test',
                    443,
                    ['203.0.113.10']
                );

        $this->assertTrue(
            $futureResult['certificate']
                ['not_yet_valid']
        );

        $this->assertFalse(
            $futureResult['certificate']['expired']
        );
    }

    public function test_certificate_chain_is_normalized_and_bounded(): void
    {
        $now = time();

        $chain = [];

        for ($index = 1; $index <= 20; $index++) {
            $chain[] = [
                'subject' => [
                    'CN' => "Certificate {$index}",
                ],
                'issuer' => [
                    'CN' => "Issuer {$index}",
                ],
                'serialNumberHex' =>
                    strtoupper(dechex($index)),
                'validFrom_time_t' =>
                    $now - 86400,
                'validTo_time_t' =>
                    $now + (30 * 86400),
            ];
        }

        $transport = new FakeTlsConnectionTransport([
            'connected' => true,
            'certificate_present' => true,
            'certificate' => [
                'subject' => [
                    'CN' => 'example.test',
                ],
                'issuer' => [
                    'CN' => 'Example Test CA',
                ],
                'validFrom_time_t' =>
                    $now - 86400,
                'validTo_time_t' =>
                    $now + (30 * 86400),
            ],
            'certificate_chain' => $chain,
            'crypto' => [
                'protocol' => 'TLSv1.3',
            ],
        ]);

        $result =
            (new TlsIntelligenceEngine($transport))
                ->inspect(
                    'example.test',
                    443,
                    ['203.0.113.10']
                );

        $this->assertSame(
            'verified',
            $result['status']
        );

        $this->assertSame(
            16,
            $result['chain_length']
        );

        $this->assertCount(
            16,
            $result['certificate_chain']
        );

        $this->assertSame(
            [
                'position',
                'subject',
                'issuer',
                'serial',
                'valid_from',
                'valid_to',
            ],
            array_keys(
                $result['certificate_chain'][0]
            )
        );

        $this->assertSame(
            1,
            $result['certificate_chain'][0]
                ['position']
        );

        $this->assertSame(
            'Certificate 1',
            $result['certificate_chain'][0]
                ['subject']
        );

        $this->assertSame(
            'Issuer 1',
            $result['certificate_chain'][0]
                ['issuer']
        );

        $this->assertSame(
            16,
            $result['certificate_chain'][15]
                ['position']
        );

        $this->assertSame(
            'Certificate 16',
            $result['certificate_chain'][15]
                ['subject']
        );
    }
}

final class FakeTlsConnectionTransport implements
    TlsConnectionTransport
{
    public int $calls = 0;

    public ?array $lastCall = null;

    public function __construct(
        private readonly array $result
    ) {}

    public function inspect(
        string $hostname,
        string $ip,
        int $port
    ): array {
        $this->calls++;

        $this->lastCall = [
            'hostname' => $hostname,
            'ip' => $ip,
            'port' => $port,
        ];

        return $this->result;
    }




}
