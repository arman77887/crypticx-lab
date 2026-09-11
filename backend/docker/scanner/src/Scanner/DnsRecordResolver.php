<?php

namespace App\Services\Scanner;

interface DnsRecordResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function query(
        string $hostname,
        int $type
    ): array;
}
