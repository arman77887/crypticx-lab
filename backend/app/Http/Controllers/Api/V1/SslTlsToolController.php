<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\SystemDnsRecordResolver;
use App\Services\Scanner\TlsIntelligenceEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class SslTlsToolController extends Controller
{
    private readonly DnsRecordResolver $dnsRecordResolver;

    public function __construct(
        private readonly TlsIntelligenceEngine $tlsIntelligenceEngine,
        ?DnsRecordResolver $dnsRecordResolver = null
    ) {
        $this->dnsRecordResolver =
            $dnsRecordResolver
            ?? new SystemDnsRecordResolver();
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => [
                'required',
                'string',
                'max:253',
            ],
            'tool' => [
                'required',
                'string',
                'in:certificate-check,tls-analysis,cipher-review,certificate-chain',
            ],
        ]);

        try {
            $hostname = $this->normalizeHostname(
                $validated['hostname']
            );

            $ips = $this->resolvePublicIps($hostname);

            $started = microtime(true);

            /*
             * The hostname is resolved and safety-checked once here.
             * TlsIntelligenceEngine receives the validated addresses
             * and connects to the pinned IP without resolving the
             * hostname again.
             */
            $tls = $this->tlsIntelligenceEngine->inspect(
                $hostname,
                443,
                $ips
            );

            if (($tls['status'] ?? null) !== 'verified') {
                throw new RuntimeException(
                    $this->failureMessage($tls)
                );
            }

            $data = match ($validated['tool']) {
                'certificate-check' =>
                    $this->certificateData(
                        $hostname,
                        $ips,
                        $tls
                    ),

                'tls-analysis' =>
                    $this->tlsData(
                        $hostname,
                        $ips,
                        $tls
                    ),

                'cipher-review' =>
                    $this->cipherData(
                        $hostname,
                        $tls
                    ),

                'certificate-chain' =>
                    $this->chainData(
                        $hostname,
                        $tls
                    ),
            };

            $data['duration_ms'] = (int) round(
                (microtime(true) - $started) * 1000
            );

            $data['checked_at'] =
                now()->toIso8601String();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'SSL/TLS analysis failed.',
            ], 502);
        }
    }

    private function certificateData(
        string $hostname,
        array $ips,
        array $tls
    ): array {
        $certificate = $tls['certificate'] ?? [];

        return [
            'hostname' => $hostname,
            'resolved_ips' => $ips,

            /*
             * Preserve the live tool's subject/issuer object shape
             * instead of replacing it with the scanner's CN shortcut.
             */
            'subject' =>
                $certificate['subject_details'] ?? [],

            'issuer' =>
                $certificate['issuer_details'] ?? [],

            'serial_number' =>
                $certificate['serial'] ?? null,

            'signature_type' =>
                $certificate['signature_algorithm'] ?? null,

            'valid_from' =>
                $certificate['valid_from'] ?? null,

            'valid_to' =>
                $certificate['valid_to'] ?? null,

            'days_remaining' =>
                $certificate['days_remaining'] ?? null,

            'currently_valid' =>
                ! ($certificate['expired'] ?? false)
                &&
                ! ($certificate['not_yet_valid'] ?? false)
                &&
                ($certificate['valid_from'] ?? null) !== null
                &&
                ($certificate['valid_to'] ?? null) !== null,

            /*
             * Preserve the old live API field as a string where
             * possible, while the shared engine internally keeps
             * normalized SAN objects.
             */
            'subject_alt_names' =>
                $this->subjectAltNameString(
                    $certificate[
                        'subject_alt_names'
                    ] ?? []
                ),
        ];
    }

    private function tlsData(
        string $hostname,
        array $ips,
        array $tls
    ): array {
        $connection = $tls['connection'] ?? [];

        return [
            'hostname' => $hostname,
            'resolved_ips' => $ips,
            'protocol' =>
                $connection['protocol'] ?? null,
            'cipher_name' =>
                $connection['cipher_name'] ?? null,
            'cipher_bits' =>
                $connection['cipher_bits'] ?? null,
            'cipher_version' =>
                $connection['cipher_version'] ?? null,
            'verification' =>
                'peer and hostname verification enabled',
            'port' => 443,
        ];
    }

    private function cipherData(
        string $hostname,
        array $tls
    ): array {
        $connection = $tls['connection'] ?? [];

        $cipher = (string) (
            $connection['cipher_name'] ?? ''
        );

        $aead =
            str_contains($cipher, 'GCM')
            ||
            str_contains($cipher, 'CHACHA20')
            ||
            str_contains($cipher, 'CCM');

        return [
            'hostname' => $hostname,

            'negotiated_cipher' =>
                $cipher !== '' ? $cipher : null,

            'bits' =>
                $connection['cipher_bits'] ?? null,

            'protocol' =>
                $connection['protocol'] ?? null,

            'aead' => $aead,

            'forward_secrecy_hint' =>
                str_contains($cipher, 'ECDHE')
                ||
                str_starts_with($cipher, 'TLS_'),

            'note' =>
                'This review reports the cipher negotiated by the TLS connection; it does not enumerate every cipher supported by the server.',
        ];
    }

    private function chainData(
        string $hostname,
        array $tls
    ): array {
        $chain = isset($tls['certificate_chain']) &&
            is_array($tls['certificate_chain'])
                ? $tls['certificate_chain']
                : [];

        $items = [];

        foreach ($chain as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'position' =>
                    $item['position'] ?? null,

                /*
                 * Old endpoint returned subject/issuer arrays.
                 * The shared chain is intentionally normalized to CN.
                 * Keep stable field names without fabricating missing
                 * certificate attributes.
                 */
                'subject' =>
                    $item['subject'] !== null
                        ? ['CN' => $item['subject']]
                        : [],

                'issuer' =>
                    $item['issuer'] !== null
                        ? ['CN' => $item['issuer']]
                        : [],

                'serial_number' =>
                    $item['serial'] ?? null,

                'valid_from' =>
                    $item['valid_from'] ?? null,

                'valid_to' =>
                    $item['valid_to'] ?? null,
            ];
        }

        return [
            'hostname' => $hostname,
            'chain_length' => count($items),
            'chain' => $items,
        ];
    }

    private function subjectAltNameString(
        array $items
    ): ?string {
        $values = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;
            $value = $item['value'] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $values[] = match ($type) {
                'dns' => 'DNS:' . $value,
                'ip' => 'IP Address:' . $value,
                default => $value,
            };
        }

        return $values !== []
            ? implode(', ', $values)
            : null;
    }

    private function failureMessage(array $tls): string
    {
        return match ($tls['status'] ?? null) {
            'connection_failed' =>
                'TLS handshake or certificate verification failed.',

            'certificate_unavailable' =>
                'The server did not provide a certificate.',

            'certificate_parse_failed' =>
                'Certificate parsing failed.',

            default =>
                'TLS analysis could not be completed.',
        };
    }

    private function normalizeHostname(
        string $hostname
    ): string {
        $hostname = trim($hostname);

        if (str_contains($hostname, '://')) {
            $parsed = parse_url($hostname);

            $hostname = (string) (
                $parsed['host'] ?? ''
            );
        }

        $hostname = strtolower(
            rtrim($hostname, '.')
        );

        if (
            $hostname === ''
            ||
            filter_var(
                $hostname,
                FILTER_VALIDATE_IP
            )
        ) {
            throw new RuntimeException(
                'Enter a valid public hostname.'
            );
        }

        if (! preg_match(
            '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            $hostname
        )) {
            throw new RuntimeException(
                'Invalid hostname.'
            );
        }

        return $hostname;
    }

    private function resolvePublicIps(
        string $hostname
    ): array {
        $records = array_merge(
            $this->dnsRecordResolver->query(
                $hostname,
                DNS_A
            ),
            $this->dnsRecordResolver->query(
                $hostname,
                DNS_AAAA
            )
        );

        if ($records === []) {
            throw new RuntimeException(
                'Hostname could not be resolved.'
            );
        }

        $ips = [];

        foreach ($records as $record) {
            $ip =
                $record['ip']
                ?? $record['ipv6']
                ?? null;

            if (! is_string($ip)) {
                continue;
            }

            if (! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE
            )) {
                throw new RuntimeException(
                    'Private or reserved target addresses are not allowed.'
                );
            }

            $ips[] = $ip;
        }

        $ips = array_values(
            array_unique($ips)
        );

        if ($ips === []) {
            throw new RuntimeException(
                'No usable public address was found.'
            );
        }

        return $ips;
    }
}
