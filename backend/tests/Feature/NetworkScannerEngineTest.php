<?php

namespace Tests\Feature;

use App\Services\Scanner\NetworkScannerEngine;
use App\Services\Scanner\TcpConnector;
use RuntimeException;
use Tests\TestCase;

class NetworkScannerEngineTest extends TestCase
{
    public function test_scans_only_fixed_bounded_port_set(): void
    {
        $connector = new class implements TcpConnector {
            public array $ports = [];

            public function connect(
                string $ip,
                int $port,
                float $timeoutSeconds
            ): array {
                $this->ports[] = $port;

                return [
                    'open' => in_array(
                        $port,
                        [80, 443],
                        true
                    ),
                    'latency_ms' => 1,
                ];
            }
        };

        $engine =
            new NetworkScannerEngine($connector);

        $result = $engine->scan(
            $this->request('port-analysis')
        );

        $this->assertSame(
            17,
            $result['tested_ports']
        );

        $this->assertSame(
            2,
            $result['open_count']
        );

        $this->assertSame(
            [
                21, 22, 25, 53, 80, 110,
                143, 443, 465, 587, 993,
                995, 3306, 5432, 6379,
                8080, 8443,
            ],
            $connector->ports
        );
    }

    public function test_private_destination_is_rejected_before_connect(): void
    {
        $connector = new class implements TcpConnector {
            public int $calls = 0;

            public function connect(
                string $ip,
                int $port,
                float $timeoutSeconds
            ): array {
                $this->calls++;

                return [
                    'open' => false,
                    'latency_ms' => 0,
                ];
            }
        };

        $engine =
            new NetworkScannerEngine($connector);

        $request =
            $this->request('port-analysis');

        $request['target']['resolved_ips'] = [
            '127.0.0.1',
        ];

        try {
            $engine->scan($request);
            $this->fail(
                'Private destination was accepted.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                0,
                $connector->calls
            );

            $this->assertStringContainsString(
                'not public',
                $exception->getMessage()
            );
        }
    }

    public function test_mixed_public_private_destination_is_rejected(): void
    {
        $connector = new class implements TcpConnector {
            public int $calls = 0;

            public function connect(
                string $ip,
                int $port,
                float $timeoutSeconds
            ): array {
                $this->calls++;

                return [
                    'open' => false,
                    'latency_ms' => 0,
                ];
            }
        };

        $engine =
            new NetworkScannerEngine($connector);

        $request =
            $this->request('network-inspector');

        $request['target']['resolved_ips'] = [
            '93.184.216.34',
            '10.0.0.1',
        ];

        $this->expectException(
            RuntimeException::class
        );

        try {
            $engine->scan($request);
        } finally {
            $this->assertSame(
                0,
                $connector->calls
            );
        }
    }

    public function test_exposure_findings_are_evidence_based(): void
    {
        $connector = new class implements TcpConnector {
            public function connect(
                string $ip,
                int $port,
                float $timeoutSeconds
            ): array {
                return [
                    'open' =>
                        $port === 5432,
                    'latency_ms' => 2,
                ];
            }
        };

        $engine =
            new NetworkScannerEngine($connector);

        $result = $engine->scan(
            $this->request('exposure-review')
        );

        $this->assertSame(
            1,
            $result['finding_count']
        );

        $this->assertSame(
            5432,
            $result['findings'][0]['port']
        );

        $this->assertSame(
            'high',
            $result['findings'][0]['severity']
        );
    }

    private function request(string $tool): array
    {
        return [
            'contract_version' => 1,
            'scanner' => 'network',
            'tool' => $tool,
            'target' => [
                'id' =>
                    '11111111-1111-4111-8111-111111111111',
                'hostname' => 'example.com',
                'resolved_ips' => [
                    '93.184.216.34',
                ],
            ],
        ];
    }
}
