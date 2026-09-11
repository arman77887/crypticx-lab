<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class NetworkStandaloneRuntimeDispatchTest extends TestCase
{
    public function test_network_private_destination_is_rejected_before_connect(): void
    {
        $payload = [
            'contract_version' => 1,
            'scanner' => 'network',
            'tool' => 'port-analysis',
            'target' => [
                'id' =>
                    '11111111-1111-4111-8111-111111111111',
                'hostname' => 'example.com',
                'resolved_ips' => [
                    '127.0.0.1',
                ],
            ],
        ];

        $process = new Process(
            [
                PHP_BINARY,
                base_path('bin/scanner-runtime.php'),
            ],
            base_path(),
            null,
            json_encode($payload),
            10
        );

        $process->run();

        $decoded = json_decode(
            $process->getOutput(),
            true
        );

        $this->assertNotSame(
            0,
            $process->getExitCode()
        );

        $this->assertIsArray($decoded);

        $this->assertSame(
            false,
            $decoded['ok'] ?? null
        );

        $this->assertSame(
            'scanner_execution_failed',
            $decoded['error']['type'] ?? null
        );
    }

    public function test_unknown_scanner_mode_is_rejected(): void
    {
        $payload = [
            'contract_version' => 1,
            'scanner' => 'unexpected-mode',
            'assessment_id' => 'test-assessment',
            'target' => [
                'id' => 'test-target',
                'url' => 'https://example.com/',
                'hostname' => 'example.com',
                'scheme' => 'https',
                'port' => 443,
            ],
        ];

        $process = new Process(
            [
                PHP_BINARY,
                base_path('bin/scanner-runtime.php'),
            ],
            base_path(),
            null,
            json_encode($payload),
            10
        );

        $process->run();

        $decoded = json_decode(
            $process->getOutput(),
            true
        );

        $this->assertNotSame(
            0,
            $process->getExitCode()
        );

        $this->assertIsArray($decoded);

        $this->assertSame(
            false,
            $decoded['ok'] ?? null
        );

        $this->assertSame(
            'scanner_execution_failed',
            $decoded['error']['type'] ?? null
        );
    }
}
