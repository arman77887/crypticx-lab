<?php

namespace Tests\Feature;

use App\Services\ScannerResultValidator;
use RuntimeException;
use Tests\TestCase;

class ScannerTlsResultValidatorTest extends TestCase
{
    private function scannerResult(array $tls): array
    {
        return [
            'engine_version' => 'http-assessment-v3',
            'finding_count' => 0,
            'findings' => [],
            'tls' => $tls,
        ];
    }

    private function verifiedTls(): array
    {
        $validFrom = time() - 86400;
        $validTo = time() + (30 * 86400);

        return [
            'enabled' => true,
            'status' => 'verified',

            'connection' => [
                'ip' => '203.0.113.10',
                'port' => 443,
                'protocol' => 'TLSv1.3',
                'cipher_name' =>
                    'TLS_AES_256_GCM_SHA384',
                'cipher_bits' => 256,
                'cipher_version' => 'TLSv1.3',
            ],

            'certificate' => [
                'subject' => 'example.test',
                'issuer' => 'Example Test CA',

                'subject_details' => [
                    'CN' => 'example.test',
                ],

                'issuer_details' => [
                    'CN' => 'Example Test CA',
                ],

                'subject_alt_names' => [
                    [
                        'type' => 'dns',
                        'value' => 'example.test',
                    ],
                ],

                'valid_from' =>
                    gmdate('c', $validFrom),

                'valid_to' =>
                    gmdate('c', $validTo),

                'days_remaining' =>
                    (int) floor(
                        ($validTo - time()) / 86400
                    ),

                'expired' => false,
                'not_yet_valid' => false,
                'serial' => 'ABC123',

                'signature_algorithm' =>
                    'RSA-SHA256',

                'sha256_fingerprint' =>
                    implode(
                        ':',
                        array_fill(0, 32, 'aa')
                    ),
            ],

            'chain_length' => 1,

            'certificate_chain' => [
                [
                    'position' => 1,
                    'subject' => 'example.test',
                    'issuer' => 'Example Test CA',
                    'serial' => 'ABC123',
                    'valid_from' =>
                        gmdate('c', $validFrom),
                    'valid_to' =>
                        gmdate('c', $validTo),
                ],
            ],
        ];
    }

    public function test_valid_verified_tls_is_accepted(): void
    {
        $validated = app(
            ScannerResultValidator::class
        )->validate(
            $this->scannerResult(
                $this->verifiedTls()
            ),
            'target-1'
        );

        $this->assertSame(
            'verified',
            $validated['tls']['status']
        );

        $this->assertSame(
            'TLSv1.3',
            $validated['tls']['connection']
                ['protocol']
        );

        $this->assertSame(
            1,
            $validated['tls']['chain_length']
        );
    }

    public function test_http_tls_not_observed_is_accepted(): void
    {
        $validated = app(
            ScannerResultValidator::class
        )->validate(
            $this->scannerResult([
                'enabled' => false,
                'certificate' => null,
            ]),
            'target-1'
        );

        $this->assertFalse(
            $validated['tls']['enabled']
        );

        $this->assertSame(
            'not_observed',
            $validated['tls']['status']
        );

        $this->assertNull(
            $validated['tls']['certificate']
        );
    }

    public function test_unknown_tls_field_is_rejected(): void
    {
        $tls = $this->verifiedTls();
        $tls['attacker_controlled'] = true;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS verified payload contains unknown fields.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_chain_over_sixteen_is_rejected(): void
    {
        $tls = $this->verifiedTls();
        $tls['certificate_chain'] = [];

        for ($i = 1; $i <= 17; $i++) {
            $tls['certificate_chain'][] = [
                'position' => $i,
                'subject' => "Certificate {$i}",
                'issuer' => "Issuer {$i}",
                'serial' => (string) $i,
                'valid_from' => null,
                'valid_to' => null,
            ];
        }

        $tls['chain_length'] = 17;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS certificate chain is invalid.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_forged_chain_length_is_rejected(): void
    {
        $tls = $this->verifiedTls();
        $tls['chain_length'] = 2;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS certificate chain length is inconsistent.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_invalid_chain_position_is_rejected(): void
    {
        $tls = $this->verifiedTls();

        $tls['certificate_chain'][0]
            ['position'] = 7;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS certificate chain position is invalid.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_oversized_san_collection_is_rejected(): void
    {
        $tls = $this->verifiedTls();

        $tls['certificate']
            ['subject_alt_names'] = [];

        for ($i = 0; $i < 129; $i++) {
            $tls['certificate']
                ['subject_alt_names'][] = [
                    'type' => 'dns',
                    'value' =>
                        "host{$i}.example.test",
                ];
        }

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS subject alternative names are invalid.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_invalid_fingerprint_is_rejected(): void
    {
        $tls = $this->verifiedTls();

        $tls['certificate']
            ['sha256_fingerprint'] =
                'not-a-sha256-fingerprint';

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS certificate fingerprint is invalid.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_conflicting_validity_flags_are_rejected(): void
    {
        $tls = $this->verifiedTls();

        $tls['certificate']['expired'] = true;
        $tls['certificate']['not_yet_valid'] = true;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS certificate validity state is invalid.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_forged_days_remaining_is_rejected(): void
    {
        $tls = $this->verifiedTls();

        $tls['certificate']
            ['days_remaining'] = 900;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Scanner TLS certificate days remaining is inconsistent.'
        );

        app(ScannerResultValidator::class)
            ->validate(
                $this->scannerResult($tls),
                'target-1'
            );
    }

    public function test_structured_connection_failure_is_accepted(): void
    {
        $tls = [
            'enabled' => true,
            'status' => 'connection_failed',
            'certificate' => null,

            'connection' => [
                'ip' => '203.0.113.10',
                'port' => 443,
            ],

            'error' => [
                'type' => 'tls_connection_failed',
                'code' => 0,
                'message' =>
                    'TLS connection failed.',
            ],
        ];

        $validated = app(
            ScannerResultValidator::class
        )->validate(
            $this->scannerResult($tls),
            'target-1'
        );

        $this->assertSame(
            'connection_failed',
            $validated['tls']['status']
        );

        $this->assertSame(
            'tls_connection_failed',
            $validated['tls']['error']['type']
        );
    }
}
