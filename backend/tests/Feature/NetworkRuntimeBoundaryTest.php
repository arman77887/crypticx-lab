<?php

namespace Tests\Feature;

use App\Models\Target;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\NetworkAnalysisService;
use App\Services\Scanner\ScannerExecutionRuntime;
use RuntimeException;
use Tests\TestCase;

class NetworkRuntimeBoundaryTest extends TestCase
{
    public function test_service_passes_only_validated_public_ips_to_runtime(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return [
                    '93.184.216.34',
                    '2606:2800:220:1:248:1893:25c8:1946',
                ];
            }
        };

        $runtime = new class implements ScannerExecutionRuntime {
            public array $request = [];

            public function scan(array $request): array
            {
                $this->request = $request;

                return [
                    'hostname' => 'example.com',
                    'tested_ip' => '93.184.216.34',
                    'tested_ports' => 17,
                    'open_count' => 0,
                    'ports' => [],
                    'duration_ms' => 1,
                ];
            }
        };

        $service = new NetworkAnalysisService(
            $dns,
            $runtime
        );

        $target = new Target();
        $target->id =
            '11111111-1111-4111-8111-111111111111';
        $target->hostname = 'example.com';

        $service->analyze(
            $target,
            'port-analysis'
        );

        $this->assertSame(
            'network',
            $runtime->request['scanner']
        );

        $this->assertSame(
            1,
            $runtime->request['contract_version']
        );

        $this->assertSame(
            'port-analysis',
            $runtime->request['tool']
        );

        $this->assertSame(
            '11111111-1111-4111-8111-111111111111',
            $runtime->request['target']['id']
        );

        $this->assertSame(
            'example.com',
            $runtime->request['target']['hostname']
        );

        $this->assertSame(
            [
                '93.184.216.34',
                '2606:2800:220:1:248:1893:25c8:1946',
            ],
            $runtime->request['target']['resolved_ips']
        );
    }

    public function test_mixed_public_private_dns_answer_never_reaches_runtime(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return [
                    '93.184.216.34',
                    '10.0.0.1',
                ];
            }
        };

        $runtime = new class implements ScannerExecutionRuntime {
            public int $calls = 0;

            public function scan(array $request): array
            {
                $this->calls++;

                return [];
            }
        };

        $service = new NetworkAnalysisService(
            $dns,
            $runtime
        );

        $target = new Target();
        $target->id =
            '11111111-1111-4111-8111-111111111111';
        $target->hostname = 'example.com';

        try {
            $service->analyze(
                $target,
                'network-inspector'
            );

            $this->fail(
                'Mixed public/private DNS answer was accepted.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                0,
                $runtime->calls
            );

            $this->assertStringContainsString(
                'Private or reserved',
                $exception->getMessage()
            );
        }
    }

    public function test_empty_dns_answer_never_reaches_runtime(): void
    {
        $dns = new class implements DnsResolver {
            public function resolve(string $hostname): array
            {
                return [];
            }
        };

        $runtime = new class implements ScannerExecutionRuntime {
            public int $calls = 0;

            public function scan(array $request): array
            {
                $this->calls++;

                return [];
            }
        };

        $service = new NetworkAnalysisService(
            $dns,
            $runtime
        );

        $target = new Target();
        $target->id =
            '11111111-1111-4111-8111-111111111111';
        $target->hostname = 'example.com';

        try {
            $service->analyze(
                $target,
                'port-analysis'
            );

            $this->fail(
                'Empty DNS result was accepted.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                0,
                $runtime->calls
            );

            $this->assertStringContainsString(
                'could not be resolved',
                $exception->getMessage()
            );
        }
    }
}
