<?php

namespace Tests\Feature;

use App\Services\ScannerResultValidator;
use RuntimeException;
use Tests\TestCase;

class ScannerDnsResultValidatorTest extends TestCase
{
    private function dns(): array
    {
        return [
            'engine_version' => 'dns-intelligence-v1',
            'hostname' => 'example.test',

            'records' => [
                'A' => [[
                    'host' => 'example.test',
                    'type' => 'A',
                    'ttl' => 300,
                    'ip' => '8.8.8.8',
                ]],

                'AAAA' => [],
                'CNAME' => [],
                'MX' => [],

                'NS' => [
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

                'TXT' => [[
                    'host' => 'example.test',
                    'type' => 'TXT',
                    'ttl' => 300,
                    'txt' => 'v=spf1 -all',
                ]],

                'CAA' => [[
                    'host' => 'example.test',
                    'type' => 'CAA',
                    'ttl' => 300,
                    'tag' => 'issue',
                    'value' => 'letsencrypt.org',
                ]],

                'SOA' => [],
            ],

            'summary' => [
                'a' => 1,
                'aaaa' => 0,
                'cname' => 0,
                'mx' => 0,
                'ns' => 2,
                'txt' => 1,
                'caa' => 1,
                'soa' => 0,
            ],

            'security' => [
                'spf_present' => true,
                'dmarc_present' => true,
                'caa_present' => true,
                'multiple_nameservers' => true,
            ],

            'dmarc_records' => [[
                'host' => '_dmarc.example.test',
                'type' => 'TXT',
                'ttl' => 300,
                'txt' => 'v=DMARC1; p=reject',
            ]],

            'duration_ms' => 25,
        ];
    }

    private function scannerResult(?array $dns = null): array
    {
        return [
            'engine_version' => 'http-assessment-v3',
            'dns_intelligence' => $dns ?? $this->dns(),
            'finding_count' => 0,
            'findings' => [],
        ];
    }

    public function test_valid_dns_payload_is_accepted(): void
    {
        $validated = app(
            ScannerResultValidator::class
        )->validate(
            $this->scannerResult(),
            'target-1'
        );

        $this->assertSame(
            'dns-intelligence-v1',
            $validated['dns_intelligence']['engine_version']
        );

        $this->assertSame(
            'example.test',
            $validated['dns_intelligence']['hostname']
        );

        $this->assertSame(
            2,
            $validated['dns_intelligence']['summary']['ns']
        );
    }

    public function test_wrong_dns_engine_version_is_rejected(): void
    {
        $dns = $this->dns();
        $dns['engine_version'] = 'forged-dns-engine';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS intelligence engine version is invalid.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_forged_record_count_is_rejected(): void
    {
        $dns = $this->dns();
        $dns['summary']['a'] = 99;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS summary a is inconsistent.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_oversized_txt_is_rejected(): void
    {
        $dns = $this->dns();

        $dns['records']['TXT'][0]['txt'] =
            str_repeat('A', 4097);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS TXT record exceeds the limit.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_too_many_records_are_rejected(): void
    {
        $dns = $this->dns();

        $dns['records']['A'] = array_fill(
            0,
            101,
            [
                'host' => 'example.test',
                'type' => 'A',
                'ttl' => 300,
                'ip' => '8.8.8.8',
            ]
        );

        $dns['summary']['a'] = 101;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS A records exceed the limit.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_forged_spf_state_is_rejected(): void
    {
        $dns = $this->dns();
        $dns['security']['spf_present'] = false;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS SPF security summary is inconsistent.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_forged_dmarc_state_is_rejected(): void
    {
        $dns = $this->dns();
        $dns['security']['dmarc_present'] = false;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS DMARC security summary is inconsistent.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_forged_caa_state_is_rejected(): void
    {
        $dns = $this->dns();
        $dns['security']['caa_present'] = false;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS CAA security summary is inconsistent.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_forged_nameserver_state_is_rejected(): void
    {
        $dns = $this->dns();

        $dns['security']['multiple_nameservers'] =
            false;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS nameserver security summary is inconsistent.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_unknown_record_field_is_rejected(): void
    {
        $dns = $this->dns();

        $dns['records']['A'][0]['evil_payload'] =
            'unexpected';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS record contains an unsupported field.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }

    public function test_excessive_duration_is_rejected(): void
    {
        $dns = $this->dns();
        $dns['duration_ms'] = 120001;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner DNS intelligence duration is invalid.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult($dns),
            'target-1'
        );
    }
}
