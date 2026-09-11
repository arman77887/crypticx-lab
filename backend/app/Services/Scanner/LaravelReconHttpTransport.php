<?php

namespace App\Services\Scanner;

use Illuminate\Support\Facades\Http;

final class LaravelReconHttpTransport implements ReconHttpTransport
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
    ): array {
        $started = microtime(true);

        if (
            $maxResponseBytes < 1 ||
            $maxResponseBytes > 1_048_576
        ) {
            throw new \InvalidArgumentException(
                'Invalid recon response size limit.'
            );
        }

        $requestHeaders =
            $this->normalizeHeaders($requestHeaders);

        try {
            $response = Http::timeout(
                $requestTimeoutSeconds
            )
                ->connectTimeout(
                    $connectTimeoutSeconds
                )
                ->withHeaders($requestHeaders)
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                    'stream' => true,
                    'curl' => [
                        CURLOPT_RESOLVE =>
                            $this->resolveEntries(
                                $host,
                                $port,
                                $resolvedIps
                            ),
                        CURLOPT_PROXY => '',
                        CURLOPT_NOPROXY => '*',
                        CURLOPT_LOW_SPEED_LIMIT => 1,
                        CURLOPT_LOW_SPEED_TIME => 5,
                    ],
                ])
                ->get($url);
        } catch (\Throwable) {
            return [
                'ok' => false,
                'duration_ms' =>
                    $this->durationMs($started),
                'error' => [
                    'type' => 'http_request_failed',
                ],
            ];
        }

        $stream =
            $response->toPsrResponse()->getBody();

        $body = '';
        $bytes = 0;

        while (!$stream->eof()) {
            $remaining =
                $maxResponseBytes - $bytes + 1;

            if ($remaining <= 0) {
                break;
            }

            $chunk = $stream->read(
                min(8192, $remaining)
            );

            if ($chunk === '') {
                if ($stream->eof()) {
                    break;
                }

                continue;
            }

            $bytes += strlen($chunk);

            if ($bytes > $maxResponseBytes) {
                return [
                    'ok' => false,
                    'duration_ms' =>
                        $this->durationMs($started),
                    'error' => [
                        'type' =>
                            'response_too_large',
                        'limit_bytes' =>
                            $maxResponseBytes,
                    ],
                ];
            }

            $body .= $chunk;
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'successful' =>
                $response->successful(),
            'headers' => $response->headers(),
            'body' => $body,
            'body_bytes' => $bytes,
            'duration_ms' =>
                $this->durationMs($started),
            'error' => null,
        ];
    }

    private function normalizeHeaders(
        array $headers
    ): array {
        if (count($headers) > 16) {
            throw new \InvalidArgumentException(
                'Too many recon request headers.'
            );
        }

        $normalized = [];
        $bytes = 0;

        foreach ($headers as $name => $value) {
            if (
                !is_string($name) ||
                !is_string($value)
            ) {
                throw new \InvalidArgumentException(
                    'Recon headers must be string pairs.'
                );
            }

            $name = trim($name);
            $value = trim($value);

            if (
                $name === '' ||
                strlen($name) > 128 ||
                preg_match(
                    "/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/",
                    $name
                ) !== 1 ||
                strlen($value) > 4096 ||
                str_contains($value, "\r") ||
                str_contains($value, "\n")
            ) {
                throw new \InvalidArgumentException(
                    'Invalid recon request header.'
                );
            }

            if (
                in_array(
                    strtolower($name),
                    [
                        'host',
                        'connection',
                        'content-length',
                        'transfer-encoding',
                        'proxy-authorization',
                        'proxy-connection',
                    ],
                    true
                )
            ) {
                throw new \InvalidArgumentException(
                    'Restricted recon request header.'
                );
            }

            $bytes +=
                strlen($name) +
                strlen($value) +
                4;

            if ($bytes > 16384) {
                throw new \InvalidArgumentException(
                    'Recon headers exceed size limit.'
                );
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    private function resolveEntries(
        string $host,
        int $port,
        array $ips
    ): array {
        $entries = [];

        foreach ($ips as $ip) {
            $entries[] = sprintf(
                '%s:%d:%s',
                $host,
                $port,
                $ip
            );
        }

        return $entries;
    }

    private function durationMs(
        float $started
    ): int {
        return (int) round(
            (microtime(true) - $started) * 1000
        );
    }
}
