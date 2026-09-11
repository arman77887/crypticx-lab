<?php

namespace Tests\Feature;

use App\Services\HttpAssessmentService;
use App\Services\Scanner\ScannerExecutionRuntime;
use Tests\TestCase;

class HttpScannerSuccessfulPathTest extends TestCase
{
    public function test_successful_scan_path_uses_isolated_process_result(): void
    {
        $process = new class implements ScannerExecutionRuntime {
            public int $calls = 0;

            public array $lastRequest = [];

            public function scan(array $request): array
            {
                $this->calls++;
                $this->lastRequest = $request;

                return [
                    'engine_version' => 'http-assessment-v3',
                    'http_status' => 200,
                    'successful' => true,
                    'duration_ms' => 7,
                    'resolved_ips' => [
                        '93.184.216.34',
                    ],
                    'findings' => [],
                    'finding_count' => 0,
                ];
            }
        };

        $this->app->instance(
            ScannerExecutionRuntime::class,
            $process
        );

        $scanner = $this->app->make(
            HttpAssessmentService::class
        );

        $request = [
            'contract_version' => 1,
            'assessment_id' => 'scanner-success-path-test',
            'target' => [
                'id' => 'target-success-path-test',
                'url' => 'http://93.184.216.34/',
                'hostname' => '93.184.216.34',
                'scheme' => 'http',
                'port' => 80,
            ],
        ];

        $result = $scanner->scan($request);

        $this->assertSame(1, $process->calls);
        $this->assertSame(
            $request,
            $process->lastRequest
        );

        $this->assertSame(
            'http-assessment-v3',
            $result['engine_version']
        );

        $this->assertSame(
            200,
            $result['http_status']
        );

        $this->assertTrue(
            $result['successful']
        );

        $this->assertSame(
            ['93.184.216.34'],
            $result['resolved_ips']
        );

        $this->assertSame(
            [],
            $result['findings']
        );

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }
}
