<?php

namespace Tests\Feature;

use App\Services\Scanner\BoundedProcessRunner;
use App\Services\Scanner\DockerScannerRuntime;
use App\Services\Scanner\IsolatedScannerProcess;
use App\Services\Scanner\ScannerExecutionRuntime;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class DockerScannerRuntimeTest extends TestCase
{
    public function test_container_command_has_mandatory_security_controls(): void
    {
        config([
            'scanner.container.image' =>
                'crypticx/scanner-runtime:test',
            'scanner.container.network' =>
                'crypticx-scanner-egress',
            'scanner.container.memory' => '256m',
            'scanner.container.cpus' => '0.50',
            'scanner.container.pids_limit' => 64,
        ]);

        $runtime = new DockerScannerRuntime(
            new BoundedProcessRunner()
        );

        $reflection = new ReflectionClass($runtime);
        $method = $reflection->getMethod('command');

        $command = $method->invoke($runtime);

        $this->assertIsArray($command);

        $this->assertSame('docker', $command[0]);
        $this->assertSame('run', $command[1]);

        foreach ([
            '--rm',
            '--interactive',
            '--read-only',
            '--cap-drop=ALL',
            '--security-opt=no-new-privileges',
            '--user=10001:10001',
            '--pids-limit=64',
            '--memory=256m',
            '--cpus=0.50',
            '--tmpfs=/tmp:rw,noexec,nosuid,nodev,size=16m',
            '--network=crypticx-scanner-egress',
        ] as $required) {
            $this->assertContains(
                $required,
                $command,
                "Missing container control: {$required}"
            );
        }

        $this->assertSame(
            'crypticx/scanner-runtime:test',
            $command[array_key_last($command)]
        );
    }

    public function test_container_command_has_no_host_mount_or_shell_primitive(): void
    {
        $runtime = new DockerScannerRuntime(
            new BoundedProcessRunner()
        );

        $reflection = new ReflectionClass($runtime);
        $method = $reflection->getMethod('command');

        $command = $method->invoke($runtime);
        $joined = implode(' ', $command);

        foreach ([
            '--volume',
            '-v',
            '--mount',
            '/var/run/docker.sock:',
            '.env',
            'sh -c',
            'bash -c',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $joined
            );
        }

        $source = file_get_contents(
            app_path('Services/Scanner/DockerScannerRuntime.php')
        );

        $this->assertIsString($source);

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

    public function test_process_mode_resolves_only_process_runtime(): void
    {
        config(['scanner.runtime' => 'process']);

        $runtime = app(ScannerExecutionRuntime::class);

        $this->assertInstanceOf(
            IsolatedScannerProcess::class,
            $runtime
        );
    }

    public function test_container_mode_resolves_only_container_runtime(): void
    {
        config(['scanner.runtime' => 'container']);

        $runtime = app(ScannerExecutionRuntime::class);

        $this->assertInstanceOf(
            DockerScannerRuntime::class,
            $runtime
        );

        $this->assertNotInstanceOf(
            IsolatedScannerProcess::class,
            $runtime
        );
    }

    public function test_unknown_runtime_fails_closed(): void
    {
        config(['scanner.runtime' => 'unexpected']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unsupported scanner execution runtime.'
        );

        app(ScannerExecutionRuntime::class);
    }

    public function test_oversized_request_is_rejected_before_docker_execution(): void
    {
        $runtime = new DockerScannerRuntime(
            new BoundedProcessRunner()
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner request exceeds the runtime input limit.'
        );

        $runtime->scan([
            'contract_version' => 1,
            'assessment_id' => str_repeat('A', 70_000),
        ]);
    }

    public function test_memory_below_safety_floor_is_rejected(): void
    {
        config([
            'scanner.container.memory' => '63m',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner container memory configuration is invalid.'
        );

        $this->invokeContainerCommand();
    }

    public function test_memory_above_safety_ceiling_is_rejected(): void
    {
        config([
            'scanner.container.memory' => '4097m',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner container memory configuration is invalid.'
        );

        $this->invokeContainerCommand();
    }

    public function test_absurd_memory_value_is_rejected(): void
    {
        config([
            'scanner.container.memory' => '999999g',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner container memory configuration is invalid.'
        );

        $this->invokeContainerCommand();
    }

    public function test_memory_boundary_values_are_accepted(): void
    {
        foreach (['64m', '4g'] as $memory) {
            config([
                'scanner.container.memory' => $memory,
                'scanner.container.pids_limit' => 64,
                'scanner.container.cpus' => '0.50',
            ]);

            $command = $this->invokeContainerCommand();

            $this->assertContains(
                '--memory=' . $memory,
                $command
            );
        }
    }

    public function test_pid_limit_below_minimum_is_rejected(): void
    {
        config([
            'scanner.container.pids_limit' => 15,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner container PID configuration is invalid.'
        );

        $this->invokeContainerCommand();
    }

    public function test_pid_limit_above_maximum_is_rejected(): void
    {
        config([
            'scanner.container.pids_limit' => 513,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner container PID configuration is invalid.'
        );

        $this->invokeContainerCommand();
    }

    public function test_pid_boundary_values_are_accepted(): void
    {
        foreach ([16, 512] as $pids) {
            config([
                'scanner.container.memory' => '256m',
                'scanner.container.pids_limit' => $pids,
                'scanner.container.cpus' => '0.50',
            ]);

            $command = $this->invokeContainerCommand();

            $this->assertContains(
                '--pids-limit=' . $pids,
                $command
            );
        }
    }

    private function invokeContainerCommand(): array
    {
        $runtime = new DockerScannerRuntime(
            new BoundedProcessRunner()
        );

        $reflection = new ReflectionClass($runtime);
        $method = $reflection->getMethod('command');

        return $method->invoke($runtime);
    }

}
