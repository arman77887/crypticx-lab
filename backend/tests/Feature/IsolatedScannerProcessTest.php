<?php

namespace Tests\Feature;

use App\Services\Scanner\IsolatedScannerProcess;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class IsolatedScannerProcessTest extends TestCase
{
    public function test_minimal_environment_does_not_include_application_secrets(): void
    {
        $service = new IsolatedScannerProcess();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('minimalEnvironment');

        $environment = $method->invoke($service);

        $this->assertIsArray($environment);

        $allowed = [
            'PATH',
            'HOME',
            'LANG',
            'LC_ALL',
            'SSL_CERT_FILE',
            'SSL_CERT_DIR',
        ];

        foreach ($environment as $name => $value) {
            if (in_array($name, $allowed, true)) {
                $this->assertIsString($value);
                $this->assertNotSame('', $value);

                continue;
            }

            /*
             * Symfony Process uses false to remove an inherited
             * environment variable from the child process.
             */
            $this->assertFalse(
                $value,
                sprintf(
                    'Inherited environment variable [%s] was not denied.',
                    $name
                )
            );
        }
    }

    public function test_oversized_request_is_rejected_before_process_execution(): void
    {
        $service = new IsolatedScannerProcess();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner request exceeds the runtime input limit.'
        );

        $service->scan([
            'contract_version' => 1,
            'assessment_id' => str_repeat('A', 70_000),
        ]);
    }

    public function test_invalid_contract_is_rejected_through_runtime_boundary(): void
    {
        $service = new IsolatedScannerProcess();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner runtime execution failed.'
        );

        $service->scan([
            'contract_version' => 999,
            'assessment_id' => 'isolated-runtime-test',
            'target' => [
                'id' => 'isolated-target-test',
                'url' => 'http://93.184.216.34/',
                'hostname' => '93.184.216.34',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);
    }

    public function test_process_uses_array_command_without_shell_primitives(): void
    {
        $source = file_get_contents(
            app_path('Services/Scanner/IsolatedScannerProcess.php')
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'new Process(',
            $source
        );
        $this->assertStringContainsString(
            'PHP_BINARY',
            $source
        );

        foreach ([
            'shell_exec(',
            'exec(',
            'system(',
            'passthru(',
            'proc_open(',
        ] as $primitive) {
            $this->assertStringNotContainsString(
                $primitive,
                $source
            );
        }
    }
}
