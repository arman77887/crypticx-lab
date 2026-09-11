<?php

namespace App\Services\Scanner;

interface DnsResolver
{
    /**
     * Resolve A/AAAA records for a hostname.
     *
     * @return array<int, string>
     */
    public function resolve(string $hostname): array;
}
