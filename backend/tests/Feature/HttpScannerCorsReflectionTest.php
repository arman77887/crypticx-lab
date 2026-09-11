<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use Tests\TestCase;

class HttpScannerCorsReflectionTest extends TestCase
{
    private function scanWithProbe(
        string $mode
    ): array {
        $transport = new class($mode)
            implements HttpTransport
        {
            public array $calls = [];

            public function __construct(
                private readonly string $mode
            ) {}

            public function get(
                string $url,
                string $host,
                int $port,
                array $resolvedIps,
                int $connectTimeoutSeconds,
                int $requestTimeoutSeconds,
                int $maxResponseBytes,
                array $requestHeaders = []
            ): array {
                $this->calls[] = [
                    'url' => $url,
                    'host' => $host,
                    'port' => $port,
                    'resolved_ips' => $resolvedIps,
                    'request_headers' => $requestHeaders,
                ];

                /*
                 * Primary assessment request.
                 */
                if ($requestHeaders === []) {
                    return $this->response([]);
                }

                /*
                 * The only secondary request expected by this contract
                 * is the bounded synthetic CORS Origin probe.
                 */
                $origin = $requestHeaders['Origin'] ?? null;

                if ($origin !== 'https://cors-probe.invalid') {
                    return [
                        'ok' => false,
                        'duration_ms' => 1,
                        'error' => [
                            'type' => 'unexpected_probe',
                            'retryable' => false,
                        ],
                    ];
                }

                return match ($this->mode) {
                    'reflect' => $this->response([
                        'access-control-allow-origin' => [
                            $origin,
                        ],
                    ]),

                    'reflect_credentials' =>
                        $this->response([
                            'access-control-allow-origin' => [
                                $origin,
                            ],
                            'access-control-allow-credentials' => [
                                'true',
                            ],
                        ]),

                    'different_origin' =>
                        $this->response([
                            'access-control-allow-origin' => [
                                'https://trusted.example',
                            ],
                        ]),

                    'wildcard' => $this->response([
                        'access-control-allow-origin' => [
                            '*',
                        ],
                    ]),

                    'failure' => [
                        'ok' => false,
                        'duration_ms' => 2,
                        'error' => [
                            'type' => 'timeout',
                            'retryable' => true,
                        ],
                    ],

                    default => $this->response([]),
                };
            }

            private function response(
                array $extraHeaders
            ): array {
                return [
                    'ok' => true,
                    'status' => 200,
                    'successful' => true,
                    'duration_ms' => 5,
                    'headers' => array_merge(
                        [
                            'content-type' => [
                                'text/html',
                            ],
                            'content-security-policy' => [
                                "default-src 'self'; " .
                                "script-src 'self'; " .
                                "object-src 'none'",
                            ],
                            'x-content-type-options' => [
                                'nosniff',
                            ],
                            'referrer-policy' => [
                                'strict-origin',
                            ],
                            'permissions-policy' => [
                                'geolocation=()',
                            ],
                        ],
                        $extraHeaders
                    ),
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $destinationResolver =
            new class implements DnsResolver
            {
                public function resolve(
                    string $hostname
                ): array {
                    return ['8.8.8.8'];
                }
            };

        $recordResolver =
            new class implements DnsRecordResolver
            {
                public function query(
                    string $hostname,
                    int $type
                ): array {
                    if ($type === DNS_A) {
                        return [[
                            'host' => $hostname,
                            'type' => 'A',
                            'ttl' => 300,
                            'ip' => '8.8.8.8',
                        ]];
                    }

                    return [];
                }
            };

        $engine = new HttpScannerEngine(
            $transport,
            $destinationResolver,
            new DnsIntelligenceEngine(
                $recordResolver
            )
        );

        $result = $engine->scan([
            'contract_version' => 1,
            'assessment_id' =>
                'assessment-cors-reflection-v1',
            'target' => [
                'id' =>
                    'target-cors-reflection-v1',
                'url' => 'http://example.test/',
                'hostname' => 'example.test',
                'scheme' => 'http',
                'port' => 80,
            ],
        ]);

        return [
            'result' => $result,
            'calls' => $transport->calls,
        ];
    }

    private function reflectionFindings(
        array $result
    ): array {
        return array_values(
            array_filter(
                $result['findings'],
                static function (
                    array $finding
                ): bool {
                    return in_array(
                        $finding['title'] ?? null,
                        [
                            'Arbitrary CORS origin reflection observed',
                            'Credentialed arbitrary CORS origin reflection observed',
                        ],
                        true
                    );
                }
            )
        );
    }

    public function test_arbitrary_origin_reflection_is_medium(): void
    {
        $scan = $this->scanWithProbe('reflect');

        $findings = $this->reflectionFindings(
            $scan['result']
        );

        $this->assertCount(1, $findings);

        $this->assertSame(
            'Arbitrary CORS origin reflection observed',
            $findings[0]['title']
        );

        $this->assertSame(
            'medium',
            $findings[0]['severity']
        );

        $this->assertSame(
            'high',
            $findings[0]['confidence']
        );

        $this->assertSame(
            'https://cors-probe.invalid',
            $findings[0]['evidence_data']
                ['probe_origin']
        );
    }

    public function test_credentialed_reflection_is_high(): void
    {
        $scan = $this->scanWithProbe(
            'reflect_credentials'
        );

        $findings = $this->reflectionFindings(
            $scan['result']
        );

        $this->assertCount(1, $findings);

        $this->assertSame(
            'Credentialed arbitrary CORS origin reflection observed',
            $findings[0]['title']
        );

        $this->assertSame(
            'high',
            $findings[0]['severity']
        );

        $this->assertSame(
            'true',
            $findings[0]['evidence_data']
                ['access_control_allow_credentials']
        );
    }

    public function test_different_origin_is_not_reflection(): void
    {
        $scan = $this->scanWithProbe(
            'different_origin'
        );

        $this->assertSame(
            [],
            $this->reflectionFindings(
                $scan['result']
            )
        );
    }

    public function test_wildcard_probe_response_is_not_reflection(): void
    {
        $scan = $this->scanWithProbe('wildcard');

        $this->assertSame(
            [],
            $this->reflectionFindings(
                $scan['result']
            )
        );
    }

    public function test_probe_failure_does_not_fabricate_finding(): void
    {
        $scan = $this->scanWithProbe('failure');

        $this->assertIsArray($scan['result']);

        $this->assertSame(
            [],
            $this->reflectionFindings(
                $scan['result']
            )
        );
    }

    public function test_probe_reuses_authorized_pinned_destination(): void
    {
        $scan = $this->scanWithProbe('reflect');

        $calls = $scan['calls'];

        $this->assertCount(2, $calls);

        $this->assertSame(
            'http://example.test/',
            $calls[0]['url']
        );

        $this->assertSame(
            $calls[0]['url'],
            $calls[1]['url']
        );

        $this->assertSame(
            $calls[0]['host'],
            $calls[1]['host']
        );

        $this->assertSame(
            $calls[0]['port'],
            $calls[1]['port']
        );

        $this->assertSame(
            ['8.8.8.8'],
            $calls[1]['resolved_ips']
        );

        $this->assertSame(
            [
                'Origin' =>
                    'https://cors-probe.invalid',
            ],
            $calls[1]['request_headers']
        );
    }
}
