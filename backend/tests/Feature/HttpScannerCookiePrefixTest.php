<?php

namespace Tests\Feature;

use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\HttpTransport;
use PHPUnit\Framework\TestCase;

class HttpScannerCookiePrefixTest extends TestCase
{
    private function scanCookie(
        string $cookie,
        string $scheme = 'http'
    ): array
    {
        $transport = new class($cookie) implements HttpTransport {
            public function __construct(
                private readonly string $cookie
            ) {
            }

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
                /*
                 * Primary request and the optional CORS probe return the
                 * same bounded fixture response. Cookie assertions below
                 * inspect only cookie_security findings.
                 */
                return [
                    'ok' => true,
                    'status' => 200,
                    'successful' => true,
                    'headers' => [
                        'set-cookie' => [
                            $this->cookie,
                        ],
                    ],
                    'duration_ms' => 1,
                    'body_bytes' => 0,
                    'error' => null,
                ];
            }
        };

        $dnsResolver = new class implements DnsResolver {
            public function resolve(string $host): array
            {
                return ['8.8.8.8'];
            }
        };

        $recordResolver = new class implements DnsRecordResolver {
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
            $dnsResolver,
            new DnsIntelligenceEngine(
                $recordResolver
            )
        );

        $result = $engine->scan([
            'contract_version' => 1,
            'assessment_id' =>
                'assessment-cookie-prefix-v1',
            'target' => [
                'id' => 'target-cookie-prefix-v1',
                'url' => "{$scheme}://8.8.8.8/",
                'hostname' => '8.8.8.8',
                'scheme' => $scheme,
                'port' => $scheme === 'https' ? 443 : 80,
            ],
        ]);

        return array_values(
            array_filter(
                $result['findings'] ?? [],
                static fn (array $finding): bool =>
                    ($finding['type'] ?? null) ===
                    'cookie_security'
            )
        );
    }

    private function prefixFindings(array $findings): array
    {
        return array_values(
            array_filter(
                $findings,
                static fn (array $finding): bool =>
                    str_contains(
                        (string) ($finding['title'] ?? ''),
                        'violates __'
                    )
            )
        );
    }

    private function titles(array $findings): array
    {
        return array_values(
            array_map(
                static fn (array $finding): string =>
                    (string) ($finding['title'] ?? ''),
                $findings
            )
        );
    }

    public function test_secure_prefix_without_secure_is_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Secure-session=abc; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertCount(1, $findings);

        $this->assertSame(
            'Cookie __Secure-session violates __Secure- prefix requirements',
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
    }

    public function test_valid_secure_prefix_has_no_prefix_finding(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Secure-session=abc; Secure; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertSame([], $findings);
    }

    public function test_host_prefix_missing_secure_is_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Host-session=abc; Path=/; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertSame(
            [
                'Cookie __Host-session violates __Host- Secure requirement',
            ],
            $this->titles($findings)
        );
    }

    public function test_host_prefix_wrong_path_is_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Host-session=abc; Secure; Path=/app; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertSame(
            [
                'Cookie __Host-session violates __Host- Path requirement',
            ],
            $this->titles($findings)
        );
    }

    public function test_host_prefix_missing_path_is_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Host-session=abc; Secure; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertSame(
            [
                'Cookie __Host-session violates __Host- Path requirement',
            ],
            $this->titles($findings)
        );

        $this->assertNull(
            $findings[0]['evidence_data']['observed_path'] ?? null
        );
    }

    public function test_host_prefix_with_domain_is_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Host-session=abc; Secure; Path=/; Domain=example.com; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertSame(
            [
                'Cookie __Host-session violates __Host- Domain requirement',
            ],
            $this->titles($findings)
        );
    }

    public function test_multiple_host_prefix_violations_are_independently_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Host-session=abc; Domain=example.com; Path=/app; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertSame(
            [
                'Cookie __Host-session violates __Host- Secure requirement',
                'Cookie __Host-session violates __Host- Path requirement',
                'Cookie __Host-session violates __Host- Domain requirement',
            ],
            $this->titles($findings)
        );
    }

    public function test_valid_host_prefix_has_no_prefix_finding(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Host-session=abc; Secure; Path=/; HttpOnly; SameSite=Lax',
                'https'
            )
        );

        $this->assertSame([], $findings);
    }

    public function test_prefix_matching_is_case_sensitive(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__host-session=abc; HttpOnly; SameSite=Lax'
            )
        );

        $this->assertSame([], $findings);
    }

    public function test_ordinary_cookie_does_not_enter_prefix_analyzer(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                'session=abc; HttpOnly; SameSite=Lax'
            )
        );

        $this->assertSame([], $findings);
    }

    public function test_secure_prefix_on_http_origin_is_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Secure-session=abc; Secure; HttpOnly; SameSite=Lax',
                'http'
            )
        );

        $this->assertSame(
            [
                'Cookie __Secure-session violates __Secure- secure-origin requirement',
            ],
            $this->titles($findings)
        );

        $this->assertSame(
            'http',
            $findings[0]['evidence_data']['observed_scheme'] ?? null
        );
    }

    public function test_host_prefix_on_http_origin_is_reported(): void
    {
        $findings = $this->prefixFindings(
            $this->scanCookie(
                '__Host-session=abc; Secure; Path=/; HttpOnly; SameSite=Lax',
                'http'
            )
        );

        $this->assertSame(
            [
                'Cookie __Host-session violates __Host- secure-origin requirement',
            ],
            $this->titles($findings)
        );

        $this->assertSame(
            'http',
            $findings[0]['evidence_data']['observed_scheme'] ?? null
        );
    }

}
