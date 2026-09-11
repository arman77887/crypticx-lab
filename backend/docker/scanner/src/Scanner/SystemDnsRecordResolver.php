<?php

namespace App\Services\Scanner;

final class SystemDnsRecordResolver implements DnsRecordResolver
{
    public function query(
        string $hostname,
        int $type
    ): array {
        $records = dns_get_record(
            $hostname,
            $type
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values($records);
    }
}
