<?php

namespace App\Services\Scanner;

final class TlsIntelligenceEngine
{
    public function __construct(
        private readonly TlsConnectionTransport $transport =
            new StreamTlsConnectionTransport()
    ) {}

    public function inspect(
        string $host,
        int $port,
        array $resolvedIps
    ): array {
        $ip = $resolvedIps[0] ?? null;

        if (! is_string($ip) || $ip === '') {
            return [
                'enabled' => false,
                'status' => 'not_observed',
                'certificate' => null,
                'connection' => null,
            ];
        }

        /*
         * Do not resolve the hostname here. The supplied IP must
         * already have passed the caller's destination safety gate.
         */
        $observation = $this->transport->inspect(
            $host,
            $ip,
            $port
        );

        if (($observation['connected'] ?? false) !== true) {
            return [
                'enabled' => true,
                'status' => 'connection_failed',
                'certificate' => null,
                'connection' => [
                    'ip' => $ip,
                    'port' => $port,
                ],
                'error' => [
                    'type' => 'tls_connection_failed',
                    'code' => (int) (
                        $observation['error_code'] ?? 0
                    ),
                    'message' =>
                        'TLS metadata connection could not be established.',
                ],
            ];
        }

        $crypto = isset($observation['crypto']) &&
            is_array($observation['crypto'])
                ? $observation['crypto']
                : [];

        $connection = [
            'ip' => $ip,
            'port' => $port,
            'protocol' => $crypto['protocol'] ?? null,
            'cipher_name' =>
                $crypto['cipher_name'] ?? null,
            'cipher_bits' =>
                isset($crypto['cipher_bits'])
                    ? (int) $crypto['cipher_bits']
                    : null,
            'cipher_version' =>
                $crypto['cipher_version'] ?? null,
        ];

        if (
            ($observation['certificate_present'] ?? false)
            !== true
        ) {
            return [
                'enabled' => true,
                'status' => 'certificate_unavailable',
                'certificate' => null,
                'connection' => $connection,
                'error' => [
                    'type' => 'certificate_not_captured',
                    'message' =>
                        'The TLS connection succeeded but no peer certificate was captured.',
                ],
            ];
        }

        $parsed = $observation['certificate'] ?? null;

        if (! is_array($parsed)) {
            return [
                'enabled' => true,
                'status' => 'certificate_parse_failed',
                'certificate' => null,
                'connection' => $connection,
                'error' => [
                    'type' => 'certificate_parse_failed',
                    'message' =>
                        'The peer certificate was captured but could not be parsed.',
                ],
            ];
        }

        $validFrom = isset($parsed['validFrom_time_t'])
            ? (int) $parsed['validFrom_time_t']
            : null;

        $validTo = isset($parsed['validTo_time_t'])
            ? (int) $parsed['validTo_time_t']
            : null;

        $now = time();

        $notYetValid =
            $validFrom !== null &&
            $validFrom > $now;

        $expired =
            $validTo !== null &&
            $validTo < $now;

        $daysRemaining = $validTo !== null
            ? (int) floor(
                ($validTo - $now) / 86400
            )
            : null;

        $certificateChain = $this->certificateChain(
            $observation['certificate_chain'] ?? []
        );

        return [
            'enabled' => true,
            'status' => 'verified',
            'connection' => $connection,
            'certificate' => [
                'subject' =>
                    $parsed['subject']['CN'] ?? null,

                'issuer' =>
                    $parsed['issuer']['CN'] ?? null,

                'subject_details' =>
                    isset($parsed['subject']) &&
                    is_array($parsed['subject'])
                        ? $parsed['subject']
                        : [],

                'issuer_details' =>
                    isset($parsed['issuer']) &&
                    is_array($parsed['issuer'])
                        ? $parsed['issuer']
                        : [],

                'subject_alt_names' =>
                    $this->subjectAltNames($parsed),

                'valid_from' => $validFrom !== null
                    ? gmdate('c', $validFrom)
                    : null,

                'valid_to' => $validTo !== null
                    ? gmdate('c', $validTo)
                    : null,

                'days_remaining' => $daysRemaining,
                'expired' => $expired,
                'not_yet_valid' => $notYetValid,

                'serial' =>
                    $parsed['serialNumberHex']
                    ?? $parsed['serialNumber']
                    ?? null,

                'signature_algorithm' =>
                    $parsed['signatureTypeSN']
                    ?? $parsed['signatureTypeLN']
                    ?? null,

                'sha256_fingerprint' =>
                    is_string(
                        $observation['sha256_fingerprint']
                        ?? null
                    )
                        ? $observation[
                            'sha256_fingerprint'
                        ]
                        : null,
            ],
            'chain_length' => count($certificateChain),
            'certificate_chain' => $certificateChain,
        ];
    }

    private function certificateChain(
        mixed $chain
    ): array {
        if (! is_array($chain)) {
            return [];
        }

        $items = [];

        foreach (array_slice($chain, 0, 16) as $index => $parsed) {
            if (! is_array($parsed)) {
                continue;
            }

            $validFrom = isset($parsed['validFrom_time_t'])
                ? (int) $parsed['validFrom_time_t']
                : null;

            $validTo = isset($parsed['validTo_time_t'])
                ? (int) $parsed['validTo_time_t']
                : null;

            $items[] = [
                'position' => $index + 1,
                'subject' =>
                    $parsed['subject']['CN'] ?? null,
                'issuer' =>
                    $parsed['issuer']['CN'] ?? null,
                'serial' =>
                    $parsed['serialNumberHex']
                    ?? $parsed['serialNumber']
                    ?? null,
                'valid_from' => $validFrom !== null
                    ? gmdate('c', $validFrom)
                    : null,
                'valid_to' => $validTo !== null
                    ? gmdate('c', $validTo)
                    : null,
            ];
        }

        return $items;
    }

    private function subjectAltNames(
        array $parsed
    ): array {
        $raw =
            $parsed['extensions']['subjectAltName']
            ?? null;

        if (! is_string($raw)) {
            return [];
        }

        $items = [];

        foreach (
            preg_split('/\s*,\s*/', $raw) ?: []
            as $entry
        ) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (str_starts_with($entry, 'DNS:')) {
                $items[] = [
                    'type' => 'dns',
                    'value' => substr($entry, 4),
                ];

                continue;
            }

            if (
                str_starts_with(
                    $entry,
                    'IP Address:'
                )
            ) {
                $items[] = [
                    'type' => 'ip',
                    'value' => substr(
                        $entry,
                        strlen('IP Address:')
                    ),
                ];

                continue;
            }

            $items[] = [
                'type' => 'other',
                'value' => $entry,
            ];
        }

        return $items;
    }
}
