<?php

namespace App\Services\Scanner;

interface HttpTransport
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
