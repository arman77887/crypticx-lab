<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\SslTlsToolController;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\TlsConnectionTransport;
use App\Services\Scanner\TlsIntelligenceEngine;
use Illuminate\Http\Request;
use Tests\TestCase;

class SslTlsToolContractTest extends TestCase
{
    private function controller(): SslTlsToolController
    {
        $now = time();

        $dns = new class implements DnsRecordResolver
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

        $transport = new class($now)
            implements TlsConnectionTransport
        {
            public function __construct(
                private readonly int $now
            ) {}

            public function inspect(
                string $hostname,
                string $ip,
                int $port
            ): array {
                return [
                    'connected' => true,
                    'certificate_present' => true,

                    'certificate' => [
                        'subject' => [
                            'CN' => 'example.test',
                            'O' => 'CrypticX Test',
                        ],
                        'issuer' => [
                            'CN' => 'CrypticX Test CA',
                        ],
                        'validFrom_time_t' =>
                            $this->now - 86400,
                        'validTo_time_t' =>
                            $this->now + (30 * 86400),
                        'serialNumberHex' => 'ABC123',
                        'signatureTypeSN' =>
                            'RSA-SHA256',
                        'extensions' => [
                            'subjectAltName' =>
                                'DNS:example.test, ' .
                                'DNS:www.example.test',
                        ],
                    ],

                    'sha256_fingerprint' =>
                        'aa:bb:cc:dd',

                    'certificate_chain' => [
                        [
                            'subject' => [
                                'CN' => 'example.test',
                            ],
                            'issuer' => [
                                'CN' => 'CrypticX Test CA',
                            ],
                            'serialNumberHex' =>
                                'ABC123',
                            'validFrom_time_t' =>
                                $this->now - 86400,
                            'validTo_time_t' =>
                                $this->now + (30 * 86400),
                        ],
                        [
                            'subject' => [
                                'CN' => 'CrypticX Test CA',
                            ],
                            'issuer' => [
                                'CN' => 'CrypticX Root CA',
                            ],
                            'serialNumberHex' =>
                                'DEF456',
                            'validFrom_time_t' =>
                                $this->now - (365 * 86400),
                            'validTo_time_t' =>
                                $this->now + (365 * 86400),
                        ],
                    ],

                    'crypto' => [
                        'protocol' => 'TLSv1.3',
                        'cipher_name' =>
                            'TLS_AES_256_GCM_SHA384',
                        'cipher_bits' => 256,
                        'cipher_version' => 'TLSv1.3',
                    ],
                ];
            }
        };

        return new SslTlsToolController(
            new TlsIntelligenceEngine($transport),
            $dns
        );
    }

    private function analyze(
        string $tool
    ): array {
        $request = Request::create(
            '/api/v1/tools/ssl-tls',
            'POST',
            [
                'hostname' => 'example.test',
                'tool' => $tool,
            ]
        );

        $response = $this->controller()->analyze(
            $request
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);

        return $payload['data'];
    }

    public function test_certificate_check_contract(): void
    {
        $data = $this->analyze(
            'certificate-check'
        );

        $this->assertSame(
            'example.test',
            $data['hostname']
        );

        $this->assertSame(
            ['8.8.8.8'],
            $data['resolved_ips']
        );

        $this->assertSame(
            'example.test',
            $data['subject']['CN']
        );

        $this->assertSame(
            'CrypticX Test CA',
            $data['issuer']['CN']
        );

        $this->assertSame(
            'ABC123',
            $data['serial_number']
        );

        $this->assertSame(
            'RSA-SHA256',
            $data['signature_type']
        );

        $this->assertTrue(
            $data['currently_valid']
        );

        $this->assertSame(
            'DNS:example.test, DNS:www.example.test',
            $data['subject_alt_names']
        );

        $this->assertArrayHasKey(
            'duration_ms',
            $data
        );

        $this->assertArrayHasKey(
            'checked_at',
            $data
        );
    }

    public function test_tls_analysis_contract(): void
    {
        $data = $this->analyze(
            'tls-analysis'
        );

        $this->assertSame(
            'TLSv1.3',
            $data['protocol']
        );

        $this->assertSame(
            'TLS_AES_256_GCM_SHA384',
            $data['cipher_name']
        );

        $this->assertSame(
            256,
            $data['cipher_bits']
        );

        $this->assertSame(
            'TLSv1.3',
            $data['cipher_version']
        );

        $this->assertSame(
            'peer and hostname verification enabled',
            $data['verification']
        );

        $this->assertSame(
            443,
            $data['port']
        );
    }

    public function test_cipher_review_contract(): void
    {
        $data = $this->analyze(
            'cipher-review'
        );

        $this->assertSame(
            'TLS_AES_256_GCM_SHA384',
            $data['negotiated_cipher']
        );

        $this->assertSame(
            256,
            $data['bits']
        );

        $this->assertSame(
            'TLSv1.3',
            $data['protocol']
        );

        $this->assertTrue(
            $data['aead']
        );

        $this->assertTrue(
            $data['forward_secrecy_hint']
        );

        $this->assertStringContainsString(
            'does not enumerate every cipher',
            $data['note']
        );
    }

    public function test_certificate_chain_contract(): void
    {
        $data = $this->analyze(
            'certificate-chain'
        );

        $this->assertSame(
            'example.test',
            $data['hostname']
        );

        $this->assertSame(
            2,
            $data['chain_length']
        );

        $this->assertCount(
            2,
            $data['chain']
        );

        $this->assertSame(
            1,
            $data['chain'][0]['position']
        );

        $this->assertSame(
            'example.test',
            $data['chain'][0]['subject']['CN']
        );

        $this->assertSame(
            'CrypticX Test CA',
            $data['chain'][0]['issuer']['CN']
        );

        $this->assertSame(
            'ABC123',
            $data['chain'][0]['serial_number']
        );

        $this->assertSame(
            2,
            $data['chain'][1]['position']
        );

        $this->assertSame(
            'CrypticX Test CA',
            $data['chain'][1]['subject']['CN']
        );
    }
}
