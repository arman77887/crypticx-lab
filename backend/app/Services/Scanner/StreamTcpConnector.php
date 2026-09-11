<?php

namespace App\Services\Scanner;

final class StreamTcpConnector implements TcpConnector
{
    public function connect(
        string $ip,
        int $port,
        float $timeoutSeconds
    ): array {
        $address = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        ) !== false
            ? '[' . $ip . ']'
            : $ip;

        $started = microtime(true);

        $socket = @stream_socket_client(
            "tcp://{$address}:{$port}",
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT
        );

        $open = is_resource($socket);

        if ($open) {
            fclose($socket);
        }

        return [
            'open' => $open,
            'latency_ms' => (int) round(
                (microtime(true) - $started) * 1000
            ),
        ];
    }
}
