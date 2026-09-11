<?php

namespace App\Services\Scanner;

use App\Models\Target;
use RuntimeException;

final class ReconAnalysisService
{
    private const CONNECT_TIMEOUT = 5;
    private const REQUEST_TIMEOUT = 10;
    private const MAX_HTML_BYTES = 262_144;

    private const DISCOVERY_PREFIXES = [
        'www',
        'api',
        'app',
        'portal',
        'docs',
        'blog',
        'cdn',
        'static',
        'mail',
        'status',
    ];

    public function __construct(
        private readonly DnsResolver $dnsResolver,
        private readonly DnsRecordResolver $recordResolver,
        private readonly ReconHttpTransport $httpTransport,
        private readonly RdapClient $rdapClient
    ) {
    }

    public function analyze(
        Target $target,
        string $tool
    ): array {
        $hostname =
            $this->canonicalHostname($target);

        return match ($tool) {
            'asset-discovery' =>
                $this->assetDiscovery($hostname),

            'technology-detection' =>
                $this->technologyDetection($target),

            'metadata-inspector' =>
                $this->metadataInspector($target),

            'whois' =>
                $this->rdapLookup($hostname),

            default => throw new RuntimeException(
                'Unsupported reconnaissance tool.'
            ),
        };
    }

    private function assetDiscovery(
        string $hostname
    ): array {
        $started = microtime(true);

        $wildcard =
            $this->detectWildcardDns($hostname);

        $assets = [];
        $wildcardFiltered = 0;

        foreach (
            self::DISCOVERY_PREFIXES
            as $prefix
        ) {
            $candidate =
                "{$prefix}.{$hostname}";

            $signature =
                $this->dnsSignature($candidate);

            if (
                $signature['addresses'] === [] &&
                $signature['cnames'] === []
            ) {
                continue;
            }

            if (
                $wildcard['detected'] &&
                $signature ===
                    $wildcard['signature']
            ) {
                $wildcardFiltered++;
                continue;
            }

            $assets[] = [
                'hostname' => $candidate,
                'addresses' =>
                    $signature['addresses'],
                'cnames' =>
                    $signature['cnames'],
            ];
        }

        return [
            'hostname' => $hostname,
            'mode' =>
                'bounded-dns-discovery',
            'tested_names' =>
                count(
                    self::DISCOVERY_PREFIXES
                ),
            'asset_count' =>
                count($assets),
            'assets' => $assets,
            'wildcard_dns' => [
                'detected' =>
                    $wildcard['detected'],
                'filtered_count' =>
                    $wildcardFiltered,
            ],
            'duration_ms' =>
                $this->elapsed($started),
        ];
    }

    private function detectWildcardDns(
        string $hostname
    ): array {
        $first = $this->dnsSignature(
            'cxw-' .
            bin2hex(random_bytes(8)) .
            '.' .
            $hostname
        );

        $second = $this->dnsSignature(
            'cxw-' .
            bin2hex(random_bytes(8)) .
            '.' .
            $hostname
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

    private function dnsSignature(
        string $hostname
    ): array {
        $records =
            $this->recordResolver->query(
                $hostname,
                DNS_A |
                DNS_AAAA |
                DNS_CNAME
            );

        $addresses = [];
        $cnames = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $ip =
                $record['ip'] ??
                $record['ipv6'] ??
                null;

            if (
                is_string($ip) &&
                $this->isPublicIp($ip)
            ) {
                $addresses[] = $ip;
            }

            $target =
                $record['target'] ?? null;

            if (
                is_string($target) &&
                trim($target) !== ''
            ) {
                $cnames[] = strtolower(
                    rtrim($target, '.')
                );
            }
        }

        $addresses =
            array_values(
                array_unique($addresses)
            );

        $cnames =
            array_values(
                array_unique($cnames)
            );

        sort($addresses);
        sort($cnames);

        return [
            'addresses' => $addresses,
            'cnames' => $cnames,
        ];
    }

    private function technologyDetection(
        Target $target
    ): array {
        $started = microtime(true);

        $http =
            $this->inspectHttpTarget($target);

        $body = strtolower($http['body']);

        $technologies = [];

        $server =
            $this->header(
                $http['headers'],
                'server'
            );

        $poweredBy =
            $this->header(
                $http['headers'],
                'x-powered-by'
            );

        if ($server !== null) {
            $technologies[] = [
                'source' =>
                    'HTTP Server header',
                'technology' => $server,
            ];
        }

        if ($poweredBy !== null) {
            $technologies[] = [
                'source' =>
                    'X-Powered-By header',
                'technology' => $poweredBy,
            ];
        }

        $signals = [
            'WordPress' => [
                'wp-content',
                'wp-includes',
            ],
            'Next.js' => [
                '/_next/',
                '__next_data__',
            ],
            'Nuxt' => [
                '/_nuxt/',
                '__nuxt__',
            ],
            'Drupal' => [
                'drupal-settings-json',
                '/sites/default/files/',
            ],
        ];

        foreach (
            $signals as $technology => $needles
        ) {
            foreach ($needles as $needle) {
                if (
                    str_contains(
                        $body,
                        strtolower($needle)
                    )
                ) {
                    $technologies[] = [
                        'source' =>
                            'Public HTML signal',
                        'technology' =>
                            $technology,
                    ];
                    break;
                }
            }
        }

        $unique = [];

        foreach ($technologies as $item) {
            $key =
                strtolower(
                    $item['source'] .
                    '|' .
                    $item['technology']
                );

            $unique[$key] = $item;
        }

        return [
            'hostname' => $http['hostname'],
            'url' => $http['url'],
            'http_status' =>
                $http['status'],
            'technologies' =>
                array_values($unique),
            'duration_ms' =>
                $this->elapsed($started),
        ];
    }

    private function metadataInspector(
        Target $target
    ): array {
        $started = microtime(true);

        $http =
            $this->inspectHttpTarget($target);

        $body = $http['body'];

        $title = null;
        $description = null;
        $generator = null;

        if (
            preg_match(
                '/<title[^>]*>(.*?)<\/title>/is',
                $body,
                $match
            )
        ) {
            $title = trim(
                html_entity_decode(
                    strip_tags($match[1]),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );
        }

        if (
            preg_match(
                '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/i',
                $body,
                $match
            )
        ) {
            $description = trim(
                html_entity_decode(
                    $match[1],
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );
        }

        if (
            preg_match(
                '/<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']*)/i',
                $body,
                $match
            )
        ) {
            $generator =
                trim($match[1]);
        }

        return [
            'hostname' => $http['hostname'],
            'url' => $http['url'],
            'http_status' =>
                $http['status'],
            'metadata' => [
                'title' => $title,
                'description' =>
                    $description,
                'generator' => $generator,
                'content_type' =>
                    $this->header(
                        $http['headers'],
                        'content-type'
                    ),
                'content_language' =>
                    $this->header(
                        $http['headers'],
                        'content-language'
                    ),
                'server' =>
                    $this->header(
                        $http['headers'],
                        'server'
                    ),
                'powered_by' =>
                    $this->header(
                        $http['headers'],
                        'x-powered-by'
                    ),
            ],
            'duration_ms' =>
                $this->elapsed($started),
        ];
    }

    private function rdapLookup(
        string $hostname
    ): array {
        $started = microtime(true);

        $rdap =
            $this->rdapClient
                ->lookupDomain($hostname);

        $events = [];

        foreach (
            ($rdap['events'] ?? [])
            as $event
        ) {
            if (!is_array($event)) {
                continue;
            }

            $events[] = [
                'action' =>
                    $event['eventAction']
                    ?? null,
                'date' =>
                    $event['eventDate']
                    ?? null,
            ];
        }

        $nameservers = [];

        foreach (
            ($rdap['nameservers'] ?? [])
            as $nameserver
        ) {
            if (
                is_array($nameserver) &&
                isset(
                    $nameserver['ldhName']
                )
            ) {
                $nameservers[] =
                    strtolower(
                        (string)
                        $nameserver['ldhName']
                    );
            }
        }

        return [
            'hostname' => $hostname,
            'handle' =>
                $rdap['handle'] ?? null,
            'ldh_name' =>
                $rdap['ldhName'] ?? null,
            'unicode_name' =>
                $rdap['unicodeName'] ?? null,
            'status' =>
                is_array(
                    $rdap['status'] ?? null
                )
                    ? $rdap['status']
                    : [],
            'events' => $events,
            'nameservers' =>
                array_values(
                    array_unique(
                        $nameservers
                    )
                ),
            'duration_ms' =>
                $this->elapsed($started),
        ];
    }

    private function inspectHttpTarget(
        Target $target
    ): array {
        $canonical =
            $this->canonicalHttpTarget($target);

        $ips =
            $this->resolveAndValidate(
                $canonical['host']
            );

        $response =
            $this->httpTransport->get(
                $canonical['url'],
                $canonical['host'],
                $canonical['port'],
                $ips,
                self::CONNECT_TIMEOUT,
                self::REQUEST_TIMEOUT,
                self::MAX_HTML_BYTES,
                [
                    'Accept' =>
                        'text/html,application/xhtml+xml,*/*',
                    'User-Agent' =>
                        'CrypticX-Lab-Recon/1.0',
                ]
            );

        if (
            !($response['ok'] ?? false)
        ) {
            throw new RuntimeException(
                'Target web service could not be inspected.'
            );
        }

        if (
            !is_string(
                $response['body'] ?? null
            )
        ) {
            throw new RuntimeException(
                'Invalid reconnaissance HTTP response.'
            );
        }

        return [
            'hostname' =>
                $canonical['host'],
            'url' => $canonical['url'],
            'status' =>
                (int) (
                    $response['status'] ?? 0
                ),
            'headers' =>
                $this->normalizeHeaders(
                    $response['headers']
                    ?? []
                ),
            'body' => $response['body'],
        ];
    }

    private function canonicalHostname(
        Target $target
    ): string {
        $hostname = strtolower(
            rtrim(
                trim(
                    (string)
                    $target->hostname
                ),
                '.'
            )
        );

        if (
            $hostname === '' ||
            filter_var(
                $hostname,
                FILTER_VALIDATE_IP
            ) !== false ||
            preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
                $hostname
            ) !== 1
        ) {
            throw new RuntimeException(
                'Target hostname is invalid.'
            );
        }

        return $hostname;
    }

    private function canonicalHttpTarget(
        Target $target
    ): array {
        $hostname =
            $this->canonicalHostname($target);

        $url =
            trim((string) $target->url);

        $parts = parse_url($url);

        if (!is_array($parts)) {
            throw new RuntimeException(
                'Target URL is invalid.'
            );
        }

        $scheme = strtolower(
            (string) (
                $parts['scheme'] ?? ''
            )
        );

        $host = strtolower(
            rtrim(
                (string) (
                    $parts['host'] ?? ''
                ),
                '.'
            )
        );

        if (
            !in_array(
                $scheme,
                ['http', 'https'],
                true
            ) ||
            $host !== $hostname ||
            isset($parts['user']) ||
            isset($parts['pass'])
        ) {
            throw new RuntimeException(
                'Target URL authority is invalid.'
            );
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https'
                ? 443
                : 80);

        if (
            $port < 1 ||
            $port > 65535
        ) {
            throw new RuntimeException(
                'Target port is invalid.'
            );
        }

        return [
            'url' => $url,
            'host' => $host,
            'scheme' => $scheme,
            'port' => $port,
        ];
    }

    private function resolveAndValidate(
        string $hostname
    ): array {
        $ips =
            $this->dnsResolver
                ->resolve($hostname);

        if ($ips === []) {
            throw new RuntimeException(
                'Hostname could not be resolved.'
            );
        }

        $validated = [];

        foreach ($ips as $ip) {
            if (
                !is_string($ip) ||
                !$this->isPublicIp($ip)
            ) {
                throw new RuntimeException(
                    'Private or reserved target addresses are not allowed.'
                );
            }

            $validated[] = $ip;
        }

        $validated =
            array_values(
                array_unique($validated)
            );

        if ($validated === []) {
            throw new RuntimeException(
                'No usable public target IP was found.'
            );
        }

        return $validated;
    }

    private function isPublicIp(
        string $ip
    ): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE |
            FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function normalizeHeaders(
        array $headers
    ): array {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(
                    ', ',
                    array_map(
                        'strval',
                        $value
                    )
                );
            }

            if (
                !is_scalar($value) &&
                $value !== null
            ) {
                continue;
            }

            $normalized[
                strtolower($name)
            ] = trim((string) $value);
        }

        return $normalized;
    }

    private function header(
        array $headers,
        string $name
    ): ?string {
        $value =
            $headers[
                strtolower($name)
            ] ?? null;

        return is_string($value) &&
            $value !== ''
            ? $value
            : null;
    }

    private function elapsed(
        float $started
    ): int {
        return (int) round(
            (microtime(true) - $started)
            * 1000
        );
    }
}
