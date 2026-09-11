<?php

namespace App\Services\Scanner;

use RuntimeException;

final class NetworkScannerEngine
{
    private const CONTRACT_VERSION = 1;

    private const PORTS = [
        21, 22, 25, 53, 80, 110, 143, 443,
        465, 587, 993, 995, 3306, 5432,
        6379, 8080, 8443,
    ];

    private const SERVICES = [
        21 => 'FTP',
        22 => 'SSH',
        25 => 'SMTP',
        53 => 'DNS',
        80 => 'HTTP',
        110 => 'POP3',
        143 => 'IMAP',
        443 => 'HTTPS',
        465 => 'SMTPS',
        587 => 'SMTP Submission',
        993 => 'IMAPS',
        995 => 'POP3S',
        3306 => 'MySQL',
        5432 => 'PostgreSQL',
        6379 => 'Redis',
        8080 => 'HTTP Alternate',
        8443 => 'HTTPS Alternate',
    ];

    private const CONNECT_TIMEOUT_SECONDS = 0.35;

    public function __construct(
        private readonly TcpConnector $connector
    ) {
    }

    public function scan(array $request): array
    {
        $this->validateRequest($request);

        $target = $request['target'];
        $hostname = strtolower(
            rtrim((string) $target['hostname'], '.')
        );

        $resolvedIps = array_values(
            array_unique($target['resolved_ips'])
        );

        $testedIp = $resolvedIps[0];
        $tool = (string) $request['tool'];

        $started = microtime(true);

        $ports = $this->scanPorts($testedIp);

        $result = match ($tool) {
            'port-analysis' =>
                $this->portAnalysis(
                    $hostname,
                    $testedIp,
                    $ports
                ),

            'service-discovery' =>
                $this->serviceDiscovery(
                    $hostname,
                    $testedIp,
                    $ports
                ),

            'network-inspector' =>
                $this->networkInspector(
                    $hostname,
                    $resolvedIps,
                    $ports
                ),

            'exposure-review' =>
                $this->exposureReview(
                    $hostname,
                    $testedIp,
                    $ports
                ),

            default => throw new RuntimeException(
                'Unsupported network analysis tool.'
            ),
        };

        $result['duration_ms'] = (int) round(
            (microtime(true) - $started) * 1000
        );

        return $result;
    }

    private function validateRequest(array $request): void
    {
        if (
            ($request['contract_version'] ?? null)
            !== self::CONTRACT_VERSION
        ) {
            throw new RuntimeException(
                'Unsupported scanner contract version.'
            );
        }

        if (($request['scanner'] ?? null) !== 'network') {
            throw new RuntimeException(
                'Invalid network scanner mode.'
            );
        }

        $tool = $request['tool'] ?? null;

        if (
            !is_string($tool) ||
            !in_array(
                $tool,
                [
                    'port-analysis',
                    'service-discovery',
                    'network-inspector',
                    'exposure-review',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Invalid network analysis tool.'
            );
        }

        $target = $request['target'] ?? null;

        if (!is_array($target)) {
            throw new RuntimeException(
                'Network scanner target is missing.'
            );
        }

        $id = $target['id'] ?? null;
        $hostname = $target['hostname'] ?? null;
        $resolvedIps = $target['resolved_ips'] ?? null;

        if (!is_string($id) || trim($id) === '') {
            throw new RuntimeException(
                'Network scanner target ID is invalid.'
            );
        }

        if (
            !is_string($hostname) ||
            !$this->isValidHostname($hostname)
        ) {
            throw new RuntimeException(
                'Network scanner hostname is invalid.'
            );
        }

        if (
            !is_array($resolvedIps) ||
            $resolvedIps === []
        ) {
            throw new RuntimeException(
                'Network scanner has no resolved destination.'
            );
        }

        foreach ($resolvedIps as $ip) {
            if (
                !is_string($ip) ||
                !$this->isPublicIp($ip)
            ) {
                throw new RuntimeException(
                    'Network scanner destination is not public.'
                );
            }
        }
    }

    private function scanPorts(string $ip): array
    {
        $results = [];

        foreach (self::PORTS as $port) {
            $connection = $this->connector->connect(
                $ip,
                $port,
                self::CONNECT_TIMEOUT_SECONDS
            );

            $results[] = [
                'port' => $port,
                'service' =>
                    self::SERVICES[$port] ?? 'Unknown',
                'state' =>
                    ($connection['open'] ?? false)
                        ? 'open'
                        : 'closed-or-filtered',
                'latency_ms' => max(
                    0,
                    (int) ($connection['latency_ms'] ?? 0)
                ),
            ];
        }

        return $results;
    }

    private function portAnalysis(
        string $hostname,
        string $ip,
        array $ports
    ): array {
        $open = $this->openPorts($ports);

        return [
            'hostname' => $hostname,
            'tested_ip' => $ip,
            'tested_ports' => count($ports),
            'open_count' => count($open),
            'ports' => $ports,
        ];
    }

    private function serviceDiscovery(
        string $hostname,
        string $ip,
        array $ports
    ): array {
        $services = $this->openPorts($ports);

        return [
            'hostname' => $hostname,
            'tested_ip' => $ip,
            'service_count' => count($services),
            'services' => $services,
            'note' =>
                'Service names are inferred from standard ports only. No banner grabbing or authentication attempts are performed.',
        ];
    }

    private function networkInspector(
        string $hostname,
        array $ips,
        array $ports
    ): array {
        $ipv4 = [];
        $ipv6 = [];

        foreach ($ips as $ip) {
            if (
                filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                ) !== false
            ) {
                $ipv4[] = $ip;
            } else {
                $ipv6[] = $ip;
            }
        }

        return [
            'hostname' => $hostname,
            'ipv4' => $ipv4,
            'ipv6' => $ipv6,
            'address_count' => count($ips),
            'tested_ip' => $ips[0],

            /*
             * Reverse DNS is intentionally omitted from V1.
             * It would introduce another DNS/network operation
             * that is not necessary for exposure analysis.
             */
            'reverse_dns' => null,

            'open_services' =>
                $this->openPorts($ports),
        ];
    }

    private function exposureReview(
        string $hostname,
        string $ip,
        array $ports
    ): array {
        $rules = [
            21 => [
                'medium',
                'FTP is externally reachable.',
            ],
            22 => [
                'info',
                'SSH is externally reachable.',
            ],
            25 => [
                'info',
                'SMTP is externally reachable.',
            ],
            3306 => [
                'high',
                'MySQL is externally reachable.',
            ],
            5432 => [
                'high',
                'PostgreSQL is externally reachable.',
            ],
            6379 => [
                'high',
                'Redis is externally reachable.',
            ],
        ];

        $findings = [];

        foreach ($ports as $item) {
            if (($item['state'] ?? null) !== 'open') {
                continue;
            }

            $port = $item['port'] ?? null;

            if (
                !is_int($port) ||
                !isset($rules[$port])
            ) {
                continue;
            }

            [$severity, $detail] = $rules[$port];

            $findings[] = [
                'port' => $port,
                'service' => $item['service'],
                'severity' => $severity,
                'detail' => $detail,
            ];
        }

        return [
            'hostname' => $hostname,
            'tested_ip' => $ip,
            'finding_count' => count($findings),
            'findings' => $findings,
            'note' =>
                'External reachability alone does not prove a vulnerability.',
        ];
    }

    private function openPorts(array $ports): array
    {
        return array_values(
            array_filter(
                $ports,
                fn (array $item): bool =>
                    ($item['state'] ?? null) === 'open'
            )
        );
    }

    private function isValidHostname(string $hostname): bool
    {
        $hostname = strtolower(
            rtrim(trim($hostname), '.')
        );

        return
            $hostname !== '' &&
            filter_var(
                $hostname,
                FILTER_VALIDATE_IP
            ) === false &&
            preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
                $hostname
            ) === 1;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE |
            FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
