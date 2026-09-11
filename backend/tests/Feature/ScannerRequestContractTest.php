<?php

namespace Tests\Feature;

use App\Services\HttpAssessmentService;
use RuntimeException;
use Tests\TestCase;

class ScannerRequestContractTest extends TestCase
{
    public function test_unknown_contract_version_is_rejected_before_network_io(): void
    {
        $scanner = app(HttpAssessmentService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner runtime execution failed.'
        );

        $scanner->scan([
            'contract_version' => 999,
            'assessment_id' => 'test-assessment',
            'target' => [
                'id' => 'test-target',
                'url' => 'https://example.com/',
                'hostname' => 'example.com',
                'scheme' => 'https',
                'port' => 443,
            ],
        ]);
    }

    public function test_missing_target_field_is_rejected_before_network_io(): void
    {
        $scanner = app(HttpAssessmentService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner runtime execution failed.'
        );

        $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'test-assessment',
            'target' => [
                'id' => 'test-target',
                'url' => 'https://example.com/',
                'scheme' => 'https',
                'port' => 443,
            ],
        ]);
    }

    public function test_invalid_port_is_rejected_before_network_io(): void
    {
        $scanner = app(HttpAssessmentService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner runtime execution failed.'
        );

        $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'test-assessment',
            'target' => [
                'id' => 'test-target',
                'url' => 'https://example.com/',
                'hostname' => 'example.com',
                'scheme' => 'https',
                'port' => 0,
            ],
        ]);
    }

    public function test_url_authority_mismatch_is_rejected_before_network_io(): void
    {
        $scanner = app(HttpAssessmentService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner runtime execution failed.'
        );

        $scanner->scan([
            'contract_version' => 1,
            'assessment_id' => 'test-assessment',
            'target' => [
                'id' => 'test-target',
                'url' => 'https://example.com/',
                'hostname' => 'example.org',
                'scheme' => 'https',
                'port' => 443,
            ],
        ]);
    }
}
