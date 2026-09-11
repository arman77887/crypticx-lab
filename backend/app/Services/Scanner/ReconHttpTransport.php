<?php

namespace App\Services\Scanner;

interface ReconHttpTransport
{
    public function get(
        string $url,
        string $host,
        int $port,
        array $resolvedIps,
        int $connectTimeoutSeconds,
        int $requestTimeoutSeconds,
        int $maxResponseBytes,
        array $requestHeaders = []
    ): array;
}
