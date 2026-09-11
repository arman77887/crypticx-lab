<?php

namespace Tests\Feature;

use Tests\TestCase;

class StandaloneScannerRuntimeTest extends TestCase
{
    private function runRuntime(string $stdin): array
    {
        $runtime = base_path('bin/scanner-runtime.php');

        $command = sprintf(
            '%s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($runtime)
        );

        $pipes = [];

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            base_path()
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    public function test_invalid_json_is_rejected_with_clean_json_stdout(): void
    {
        $execution = $this->runRuntime('{bad-json');

        $this->assertSame(65, $execution['exit_code']);
        $this->assertSame('', $execution['stderr']);

        $payload = json_decode(
            trim($execution['stdout']),
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(false, $payload['ok']);
        $this->assertSame(
            'runtime_invalid_json',
            $payload['error']['type']
        );
    }

    public function test_empty_input_is_rejected(): void
    {
        $execution = $this->runRuntime('');

        $this->assertSame(65, $execution['exit_code']);
        $this->assertSame('', $execution['stderr']);

        $payload = json_decode(
            trim($execution['stdout']),
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            'runtime_input_empty',
            $payload['error']['type']
        );
    }

    public function test_oversized_input_is_rejected(): void
    {
        $execution = $this->runRuntime(
            str_repeat('A', 65_537)
        );

        $this->assertSame(65, $execution['exit_code']);
        $this->assertSame('', $execution['stderr']);

        $payload = json_decode(
            trim($execution['stdout']),
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            'runtime_input_too_large',
            $payload['error']['type']
        );
    }

    public function test_unknown_scanner_contract_fails_before_execution(): void
    {
        $execution = $this->runRuntime(
            json_encode(
                [
                    'contract_version' => 999,
                    'assessment_id' => 'runtime-contract-test',
                    'target' => [
                        'id' => 'runtime-target-test',
                        'url' => 'http://93.184.216.34/',
                        'hostname' => '93.184.216.34',
                        'scheme' => 'http',
                        'port' => 80,
                    ],
                ],
                JSON_THROW_ON_ERROR
            )
        );

        $this->assertSame(1, $execution['exit_code']);
        $this->assertSame('', $execution['stderr']);

        $payload = json_decode(
            trim($execution['stdout']),
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        $this->assertFalse($payload['ok']);
        $this->assertSame(
            'scanner_execution_failed',
            $payload['error']['type']
        );
        $this->assertSame(
            'RuntimeException',
            $payload['error']['class']
        );
    }
}
