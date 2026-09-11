<?php

namespace App\Services\Scanner;

use App\Models\Target;
use RuntimeException;

final class NetworkAnalysisService
{
    public function __construct(
        private readonly DnsResolver $dnsResolver,
        private readonly ScannerExecutionRuntime $runtime
    ) {
    }

    public function analyze(
        Target $target,
        string $tool
    ): array {
        if (
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
                'Unsupported network analysis tool.'
            );
        }

        $hostname = strtolower(
            rtrim(
                trim((string) $target->hostname),
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

        $resolvedIps =
            $this->dnsResolver->resolve($hostname);

        if ($resolvedIps === []) {
            throw new RuntimeException(
                'Hostname could not be resolved.'
            );
        }

        $publicIps = [];

        foreach ($resolvedIps as $ip) {
            if (
                !is_string($ip) ||
                filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE |
                    FILTER_FLAG_NO_RES_RANGE
                ) === false
            ) {
                throw new RuntimeException(
                    'Private or reserved target addresses are not allowed.'
                );
            }

            $publicIps[] = $ip;
        }

        $publicIps = array_values(
            array_unique($publicIps)
        );

        if ($publicIps === []) {
            throw new RuntimeException(
                'No usable public target IP was found.'
            );
        }

        return $this->runtime->scan([
            'contract_version' => 1,
            'scanner' => 'network',
            'tool' => $tool,
            'target' => [
                'id' => (string) $target->id,
                'hostname' => $hostname,
                'resolved_ips' => $publicIps,
            ],
        ]);
    }
}
