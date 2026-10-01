<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CspReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_csp_report_is_accepted(): void
    {
        $response = $this->postJson(
            '/api/v1/security/csp-report',
            [
                'csp-report' => [
                    'document-uri' =>
                        'https://crypticxlab.crxhub.org/login?token=secret#fragment',
                    'blocked-uri' =>
                        'https://example.com/script.js?secret=value',
                    'effective-directive' => 'script-src-elem',
                    'violated-directive' => "script-src 'self'",
                    'line-number' => 10,
                    'column-number' => 4,
                    'status-code' => 200,
                ],
            ]
        );

        $response->assertNoContent();
    }

    public function test_malformed_json_is_safely_ignored(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/security/csp-report',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/csp-report',
            ],
            '{"broken":'
        );

        $response->assertNoContent();
    }

    public function test_oversized_report_is_rejected(): void
    {
        $payload = json_encode([
            'csp-report' => [
                'document-uri' =>
                    'https://crypticxlab.crxhub.org/',
                'blocked-uri' =>
                    str_repeat('A', 17000),
            ],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/security/csp-report',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/csp-report',
                'CONTENT_LENGTH' => (string) strlen($payload),
            ],
            $payload
        );

        $response->assertStatus(413);
    }
}
