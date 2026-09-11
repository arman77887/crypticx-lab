<?php

namespace App\Services\Scanner;

use RuntimeException;

final class DnsIntelligenceEngine
{
    private const MAX_RECORDS_PER_TYPE = 100;
    private const MAX_TXT_LENGTH = 4096;

    /*
     * Keep DNS intelligence well below the isolated scanner
     * process stdout ceiling. HTTP metadata and findings need
     * independent headroom inside the same result envelope.
     */
    private const MAX_PAYLOAD_BYTES = 524_288;

    public function __construct(
        private readonly DnsRecordResolver $resolver =
            new SystemDnsRecordResolver()
    ) {
    }

    public function analyze(string $hostname): array
    {
        $hostname = $this->normalizeHostname($hostname);

        $started = microtime(true);

        $records = [
            'A' => $this->query($hostname, DNS_A),
            'AAAA' => $this->query($hostname, DNS_AAAA),
            'CNAME' => $this->query($hostname, DNS_CNAME),
            'MX' => $this->query($hostname, DNS_MX),
            'NS' => $this->query($hostname, DNS_NS),
            'TXT' => $this->query($hostname, DNS_TXT),
            'CAA' => $this->query($hostname, DNS_CAA),
            'SOA' => $this->query($hostname, DNS_SOA),
        ];

        $dmarc = $this->query(
            '_dmarc.' . $hostname,
            DNS_TXT
        );

        $spfPresent = $this->containsPolicy(
            $records['TXT'],
            'v=spf1'
        );

        $dmarcPresent = $this->containsPolicy(
            $dmarc,
            'v=dmarc1'
        );

        $result = [
            'engine_version' => 'dns-intelligence-v1',
            'hostname' => $hostname,
            'records' => $records,
            'summary' => [
                'a' => count($records['A']),
                'aaaa' => count($records['AAAA']),
                'cname' => count($records['CNAME']),
                'mx' => count($records['MX']),
                'ns' => count($records['NS']),
                'txt' => count($records['TXT']),
                'caa' => count($records['CAA']),
                'soa' => count($records['SOA']),
            ],
            'security' => [
                'spf_present' => $spfPresent,
                'dmarc_present' => $dmarcPresent,
                'caa_present' => $records['CAA'] !== [],
                'multiple_nameservers' =>
                    count($records['NS']) >= 2,
            ],
            'dmarc_records' => $dmarc,
            'duration_ms' => max(
                0,
                (int) round(
                    (microtime(true) - $started) * 1000
                )
            ),
        ];

        $this->assertPayloadSize($result);

        return $result;
    }

    private function assertPayloadSize(array $result): void
    {
        try {
            $encoded = json_encode(
                $result,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            throw new RuntimeException(
                'DNS intelligence result is not valid JSON.'
            );
        }

        if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new RuntimeException(
                'DNS intelligence result exceeds the size limit.'
            );
        }
    }

    private function query(
        string $hostname,
        int $type
    ): array {
        $records = $this->resolver->query(
            $hostname,
            $type
        );

        $records = array_slice(
            $records,
            0,
            self::MAX_RECORDS_PER_TYPE
        );

        $normalized = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $normalized[] = $this->normalizeRecord(
                $record
            );
        }

        return $normalized;
    }

    private function normalizeRecord(array $record): array
    {
        $allowed = [
            'host',
            'class',
            'ttl',
            'type',
            'ip',
            'ipv6',
            'target',
            'pri',
            'weight',
            'port',
            'txt',
            'entries',
            'value',
            'tag',
            'mname',
            'rname',
            'serial',
            'refresh',
            'retry',
            'expire',
            'minimum-ttl',
        ];

        $normalized = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $record)) {
                continue;
            }

            $value = $record[$key];

            if ($key === 'txt' && is_string($value)) {
                $value = substr(
                    $value,
                    0,
                    self::MAX_TXT_LENGTH
                );
            }

            if ($key === 'entries' && is_array($value)) {
                $value = array_slice(
                    array_map(
                        static fn ($entry) =>
                            substr(
                                (string) $entry,
                                0,
                                self::MAX_TXT_LENGTH
                            ),
                        $value
                    ),
                    0,
                    32
                );
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function containsPolicy(
        array $records,
        string $prefix
    ): bool {
        $prefix = strtolower($prefix);

        foreach ($records as $record) {
            $txt = $record['txt'] ?? null;

            if (
                is_string($txt) &&
                str_starts_with(
                    strtolower(trim($txt)),
                    $prefix
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHostname(
        string $hostname
    ): string {
        $hostname = strtolower(
            rtrim(trim($hostname), '.')
        );

        if (
            $hostname === '' ||
            strlen($hostname) > 253 ||
            filter_var(
                $hostname,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) === false
        ) {
            throw new RuntimeException(
                'DNS intelligence hostname is invalid.'
            );
        }

        return $hostname;
    }
}
