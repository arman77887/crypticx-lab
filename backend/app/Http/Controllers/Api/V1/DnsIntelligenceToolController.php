<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Services\Scanner\DnsIntelligenceEngine;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class DnsIntelligenceToolController extends Controller
{
    public function __construct(
        private readonly DnsIntelligenceEngine $dnsIntelligenceEngine
    ) {
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
            'tool' => [
                'required',
                'string',
                'in:record-inspector,subdomain-discovery,dns-health',
            ],
        ]);

        try {
            $hostname = $this->normalizeHostname($validated['hostname']);
            $tool = $validated['tool'];
            $target = null;

            if ($tool === 'subdomain-discovery') {
                $target = Target::query()
                    ->where('user_id', $request->user()->id)
                    ->whereRaw('LOWER(hostname) = ?', [$hostname])
                    ->where('authorization_confirmed', true)
                    ->where('status', 'active')
                    ->first();

                if (! $target) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Subdomain discovery requires an active authorized target registered to your account.',
                    ], 403);
                }
            }

            $response = match ($tool) {
                'record-inspector' => $this->recordInspector($hostname),
                'subdomain-discovery' => $this->subdomainDiscovery($hostname),
                'dns-health' => $this->dnsHealth($hostname),
            };

            if ($tool === 'subdomain-discovery') {
                app(TelemetryService::class)->audit(
                    $request,
                    'tool.dns_subdomain_discovery',
                    'security_tool',
                    $request->user(),
                    [
                        'hostname' => $hostname,
                        'status' => 'completed',
                    ],
                    'target',
                    $target->id,
                );
            }

            return $response;
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'DNS intelligence analysis failed.',
            ], 502);
        }
    }

    private function recordInspector(string $hostname): JsonResponse
    {
        $result = $this->dnsIntelligenceEngine->analyze(
            $hostname
        );

        $records = array_filter(
            $result['records'],
            static fn (array $items): bool =>
                $items !== []
        );

        $recordCount = array_sum(
            array_map(
                'count',
                $result['records']
            )
        );

        return response()->json([
            'success' => true,
            'data' => [
                'hostname' => $result['hostname'],
                'records' => $records,
                'record_count' => $recordCount,
                'types' => array_keys($records),
                'duration_ms' => $result['duration_ms'],
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function subdomainDiscovery(string $hostname): JsonResponse
    {
        $started = microtime(true);

        /*
         * Deliberately bounded discovery.
         * No large brute-force wordlist is used.
         */
        $prefixes = [
            'www',
            'api',
            'app',
            'admin',
            'dev',
            'staging',
            'test',
            'mail',
            'cdn',
            'static',
            'blog',
            'docs',
        ];

        $found = [];

        $wildcard = $this->detectWildcardDns($hostname);
        $wildcardFiltered = 0;

        foreach ($prefixes as $prefix) {
            $candidate = "{$prefix}.{$hostname}";
            $signature = $this->dnsSignature($candidate);

            if (
                $signature['addresses'] === [] &&
                $signature['cnames'] === []
            ) {
                continue;
            }

            if (
                $wildcard['detected'] &&
                $signature === $wildcard['signature']
            ) {
                $wildcardFiltered++;
                continue;
            }

            $found[] = [
                'hostname' => $candidate,
                'addresses' => $signature['addresses'],
                'cnames' => $signature['cnames'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'hostname' => $hostname,
                'mode' => 'bounded-common-prefix-discovery',
                'tested' => count($prefixes),
                'found_count' => count($found),
                'subdomains' => $found,
                'wildcard_dns' => [
                    'detected' => $wildcard['detected'],
                    'filtered_count' => $wildcardFiltered,
                ],
                'duration_ms' => (int) round(
                    (microtime(true) - $started) * 1000
                ),
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function detectWildcardDns(string $hostname): array
    {
        $first = $this->dnsSignature(
            'cxw-' . bin2hex(random_bytes(8)) . '.' . $hostname
        );

        $second = $this->dnsSignature(
            'cxw-' . bin2hex(random_bytes(8)) . '.' . $hostname
        );

        $firstExists =
            $first['addresses'] !== [] ||
            $first['cnames'] !== [];

        $secondExists =
            $second['addresses'] !== [] ||
            $second['cnames'] !== [];

        $detected =
            $firstExists &&
            $secondExists &&
            $first === $second;

        return [
            'detected' => $detected,
            'signature' => $detected
                ? $first
                : [
                    'addresses' => [],
                    'cnames' => [],
                ],
        ];
    }

    private function dnsSignature(string $hostname): array
    {
        $records = @dns_get_record(
            $hostname,
            DNS_A | DNS_AAAA | DNS_CNAME
        );

        if (! is_array($records) || $records === []) {
            return [
                'addresses' => [],
                'cnames' => [],
            ];
        }

        $addresses = [];
        $cnames = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = (string) $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $addresses[] = (string) $record['ipv6'];
            }

            if (isset($record['target'])) {
                $cnames[] = rtrim(
                    strtolower((string) $record['target']),
                    '.'
                );
            }
        }

        $addresses = array_values(array_unique($addresses));
        $cnames = array_values(array_unique($cnames));

        sort($addresses);
        sort($cnames);

        return [
            'addresses' => $addresses,
            'cnames' => $cnames,
        ];
    }

    private function dnsHealth(string $hostname): JsonResponse
    {
        $result = $this->dnsIntelligenceEngine->analyze(
            $hostname
        );

        $summary = $result['summary'];
        $security = $result['security'];

        $checks = [
            [
                'name' => 'Address records',
                'status' =>
                    (
                        $summary['a'] > 0 ||
                        $summary['aaaa'] > 0
                    )
                        ? 'pass'
                        : 'warning',
                'detail' =>
                    (
                        $summary['a'] > 0 ||
                        $summary['aaaa'] > 0
                    )
                        ? 'A or AAAA record is present.'
                        : 'No A or AAAA record was found.',
            ],
            [
                'name' => 'Authoritative nameservers',
                'status' =>
                    $security['multiple_nameservers']
                        ? 'pass'
                        : 'warning',
                'detail' =>
                    $security['multiple_nameservers']
                        ? 'Multiple NS records are configured.'
                        : 'Fewer than two NS records were observed.',
            ],
            [
                'name' => 'SOA record',
                'status' =>
                    $summary['soa'] > 0
                        ? 'pass'
                        : 'warning',
                'detail' =>
                    $summary['soa'] > 0
                        ? 'SOA record is present.'
                        : 'SOA record was not observed.',
            ],
            [
                'name' => 'CAA policy',
                'status' =>
                    $security['caa_present']
                        ? 'pass'
                        : 'info',
                'detail' =>
                    $security['caa_present']
                        ? 'CAA record is configured.'
                        : 'No CAA record was observed.',
            ],
            [
                'name' => 'SPF policy',
                'status' =>
                    $security['spf_present']
                        ? 'pass'
                        : 'info',
                'detail' =>
                    $security['spf_present']
                        ? 'SPF policy was detected.'
                        : 'No SPF policy was detected.',
            ],
            [
                'name' => 'DMARC policy',
                'status' =>
                    $security['dmarc_present']
                        ? 'pass'
                        : 'info',
                'detail' =>
                    $security['dmarc_present']
                        ? 'DMARC policy was detected.'
                        : 'No DMARC policy was detected.',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'hostname' => $result['hostname'],
                'summary' => [
                    'a' => $summary['a'],
                    'aaaa' => $summary['aaaa'],
                    'ns' => $summary['ns'],
                    'mx' => $summary['mx'],
                    'txt' => $summary['txt'],
                    'caa' => $summary['caa'],
                    'soa' => $summary['soa'],
                    'spf_present' =>
                        $security['spf_present'],
                    'dmarc_present' =>
                        $security['dmarc_present'],
                ],
                'checks' => $checks,
                'duration_ms' => $result['duration_ms'],
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = rtrim($hostname, '.');

        if (str_contains($hostname, '://')) {
            $parsed = parse_url($hostname);
            $hostname = strtolower((string) ($parsed['host'] ?? ''));
        }

        if (
            $hostname === '' ||
            filter_var($hostname, FILTER_VALIDATE_IP)
        ) {
            throw new RuntimeException(
                'Enter a valid domain hostname, not an IP address.'
            );
        }

        if (! preg_match(
            '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            $hostname
        )) {
            throw new RuntimeException('Invalid hostname.');
        }

        return $hostname;
    }
}
