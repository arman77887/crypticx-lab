<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DnsToolController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
        ]);

        $hostname = strtolower(trim($validated['hostname']));
        $hostname = rtrim($hostname, '.');

        // Accept a hostname only, never a URL/path.
        if (
            $hostname === '' ||
            filter_var($hostname, FILTER_VALIDATE_IP) ||
            ! preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
                $hostname
            )
        ) {
            throw ValidationException::withMessages([
                'hostname' => ['Enter a valid public hostname, for example example.com.'],
            ]);
        }

        $started = microtime(true);

        $records = dns_get_record(
            $hostname,
            DNS_A |
            DNS_AAAA |
            DNS_MX |
            DNS_NS |
            DNS_TXT |
            DNS_CNAME |
            DNS_CAA
        );

        if ($records === false) {
            return response()->json([
                'success' => false,
                'message' => 'DNS lookup failed.',
            ], 502);
        }

        // Do not expose/use hostnames resolving to unsafe IP space.
        foreach ($records as $record) {
            foreach (['ip', 'ipv6'] as $field) {
                $ip = $record[$field] ?? null;

                if (
                    is_string($ip) &&
                    ! filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    )
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Private, reserved, loopback, link-local, or otherwise unsafe IP targets are not allowed.',
                    ], 422);
                }
            }
        }

        $grouped = [
            'A' => [],
            'AAAA' => [],
            'MX' => [],
            'NS' => [],
            'TXT' => [],
            'CNAME' => [],
            'CAA' => [],
        ];

        foreach ($records as $record) {
            $type = strtoupper((string) ($record['type'] ?? ''));

            if (! array_key_exists($type, $grouped)) {
                continue;
            }

            $value = match ($type) {
                'A' => $record['ip'] ?? null,
                'AAAA' => $record['ipv6'] ?? null,
                'MX' => isset($record['target'])
                    ? [
                        'host' => rtrim((string) $record['target'], '.'),
                        'priority' => (int) ($record['pri'] ?? 0),
                    ]
                    : null,
                'NS', 'CNAME' => isset($record['target'])
                    ? rtrim((string) $record['target'], '.')
                    : null,
                'TXT' => $record['txt'] ?? null,
                'CAA' => isset($record['value'])
                    ? [
                        'flags' => (int) ($record['flags'] ?? 0),
                        'tag' => (string) ($record['tag'] ?? ''),
                        'value' => (string) $record['value'],
                    ]
                    : null,
                default => null,
            };

            if ($value !== null) {
                $grouped[$type][] = [
                    'value' => $value,
                    'ttl' => isset($record['ttl'])
                        ? (int) $record['ttl']
                        : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'hostname' => $hostname,
                'records' => $grouped,
                'record_count' => collect($grouped)
                    ->flatten(1)
                    ->count(),
                'duration_ms' => (int) round(
                    (microtime(true) - $started) * 1000
                ),
                'lookup_id' => (string) Str::uuid(),
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
