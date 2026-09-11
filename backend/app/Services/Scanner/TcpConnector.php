<?php

namespace App\Services\Scanner;

interface TcpConnector
{
    public function connect(
        string $ip,
        int $port,
        float $timeoutSeconds
    ): array;
}
