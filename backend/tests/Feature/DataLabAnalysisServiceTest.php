<?php

namespace Tests\Feature;

use App\Services\Labs\DataLabAnalysisService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DataLabAnalysisServiceTest extends TestCase
{
    private DataLabAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataLabAnalysisService();
    }

    public function test_csv_analysis_is_offline_and_bounded(): void
    {
        $result = $this->service->analyze(
            'csv-analyzer',
            "name,age\nAlice,28\nBob,31"
        );

        $this->assertSame('offline-csv-analysis', $result['mode']);
        $this->assertSame(2, $result['row_count']);
        $this->assertSame(2, $result['column_count']);
    }

    public function test_pattern_analysis_extracts_security_indicators(): void
    {
        $result = $this->service->analyze(
            'pattern-analysis',
            'admin@example.com CVE-2026-12345 https://example.com'
        );

        $this->assertSame(
            1,
            $result['patterns']['cve']['count']
        );

        $this->assertSame(
            1,
            $result['patterns']['email']['count']
        );
    }

    public function test_http_inspector_never_sends_network_request(): void
    {
        $result = $this->service->analyze(
            'http-inspector',
            "HTTP/1.1 200 OK\nServer: nginx\nContent-Type: text/html\n\nHello"
        );

        $this->assertFalse($result['network_request_sent']);
        $this->assertSame('response', $result['message_type']);
    }

    public function test_hash_inspector_identifies_sha256_format(): void
    {
        $result = $this->service->analyze(
            'hash-inspector',
            str_repeat('a', 64)
        );

        $this->assertContains(
            'SHA-256',
            $result['possible_algorithms']
        );

        $this->assertFalse($result['cracking_performed']);
    }

    public function test_jwt_inspector_does_not_claim_signature_verification(): void
    {
        $header = rtrim(strtr(
            base64_encode('{"alg":"HS256","typ":"JWT"}'),
            '+/',
            '-_'
        ), '=');

        $payload = rtrim(strtr(
            base64_encode('{"sub":"123"}'),
            '+/',
            '-_'
        ), '=');

        $result = $this->service->analyze(
            'jwt-inspector',
            $header . '.' . $payload . '.signature'
        );

        $this->assertFalse($result['signature_verified']);
        $this->assertSame('123', $result['payload']['sub']);
    }

    public function test_sensitive_data_redactor_removes_email(): void
    {
        $result = $this->service->analyze(
            'sensitive-data-redactor',
            'Contact admin@example.com'
        );

        $this->assertStringNotContainsString(
            'admin@example.com',
            $result['redacted_text']
        );

        $this->assertSame(1, $result['total_redactions']);
    }

    public function test_data_diff_reports_changed_values(): void
    {
        $result = $this->service->analyze(
            'data-diff',
            '{"left":{"role":"user"},"right":{"role":"admin"}}'
        );

        $this->assertFalse($result['equal']);
        $this->assertNotEmpty($result['changes']);
    }

    public function test_oversized_input_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->analyze(
            'data-cleaner',
            str_repeat(
                'A',
                DataLabAnalysisService::MAX_INPUT_BYTES + 1
            )
        );
    }
}
