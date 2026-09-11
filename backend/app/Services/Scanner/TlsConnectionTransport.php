<?php

namespace App\Services\Scanner;

interface TlsConnectionTransport
{
    /**
     * Establish a verified TLS connection to an already-authorized,
     * already-resolved IP while preserving the original hostname for
     * SNI and peer-name verification.
     *
     * @return array<string, mixed>
     */
    public function inspect(
        string $hostname,
        string $ip,
        int $port
    ): array;
}
