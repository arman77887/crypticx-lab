<?php

namespace App\Services\Scanner;

final class SystemDnsResolver implements DnsResolver
{
    public function resolve(string $hostname): array
    {
        $records = dns_get_record(
            $hostname,
            DNS_A | DNS_AAAA
        );

        if ($records === false) {
            return [];
        }

        $ips = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip) && $ip !== '') {
                $ips[] = $ip;
            }
        }

        return array_values(
            array_unique($ips)
        );
    }
}
