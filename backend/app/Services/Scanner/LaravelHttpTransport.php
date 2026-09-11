<?php

namespace App\Services\Scanner;

use Illuminate\Support\Facades\Http;

class LaravelHttpTransport implements HttpTransport
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

        $requestHeaders = $this->normalizeRequestHeaders(
            $requestHeaders
        );

        try {
            $response = Http::timeout($requestTimeoutSeconds)
                ->connectTimeout($connectTimeoutSeconds)
                ->withHeaders($requestHeaders)
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                    'stream' => true,
                    'curl' => [
                        CURLOPT_RESOLVE => $this->curlResolveEntries(
                            $host,
                            $port,
                            $resolvedIps
                        ),
                        CURLOPT_LOW_SPEED_LIMIT => 1,
                        CURLOPT_LOW_SPEED_TIME => 5,
                    ],
                ])
                ->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            return [
                'ok' => false,
                'duration_ms' => $this->durationMs($started),
                'error' => [
                    'type' => $this->classifyFailure($exception),
                    'retryable' => true,
                ],
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'duration_ms' => $this->durationMs($started),
                'error' => [
                    'type' => 'http_request_failed',
                    'retryable' => false,
                ],
            ];
        }

        $body = $response->toPsrResponse()->getBody();
        $bytesRead = 0;

        while (! $body->eof()) {
            $remaining = $maxResponseBytes - $bytesRead + 1;

            if ($remaining <= 0) {
                break;
            }

            $chunk = $body->read(
                min(8192, $remaining)
            );

            if ($chunk === '') {
                if ($body->eof()) {
                    break;
                }

                continue;
            }

            $bytesRead += strlen($chunk);

            if ($bytesRead > $maxResponseBytes) {
                return [
                    'ok' => false,
                    'duration_ms' => $this->durationMs($started),
                    'error' => [
                        'type' => 'response_too_large',
                        'retryable' => false,
                        'limit_bytes' => $maxResponseBytes,
                    ],
                ];
            }
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'headers' => $response->headers(),
            'duration_ms' => $this->durationMs($started),
            'body_bytes' => $bytesRead,
            'error' => null,
        ];
    }

    private function normalizeRequestHeaders(
        array $headers
    ): array {
        if (count($headers) > 16) {
            throw new \InvalidArgumentException(
                'Too many scanner request headers.'
            );
        }

        $normalized = [];
        $totalBytes = 0;

        foreach ($headers as $name => $value) {
            if (
                ! is_string($name) ||
                ! is_string($value)
            ) {
                throw new \InvalidArgumentException(
                    'Scanner request headers must be string pairs.'
                );
            }

            $name = trim($name);
            $value = trim($value);

            if (
                $name === '' ||
                strlen($name) > 128 ||
                ! preg_match(
                    "/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/",
                    $name
                )
            ) {
                throw new \InvalidArgumentException(
                    'Invalid scanner request header name.'
                );
            }

            if (
                strlen($value) > 4096 ||
                str_contains($value, "\r") ||
                str_contains($value, "\n")
            ) {
                throw new \InvalidArgumentException(
                    'Invalid scanner request header value.'
                );
            }

            /*
             * Keep transport-owned routing/security headers immutable.
             * Internal probes do not need to override these.
             */
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
                    'Restricted scanner request header.'
                );
            }

            $totalBytes +=
                strlen($name) +
                strlen($value) +
                4;

            if ($totalBytes > 16384) {
                throw new \InvalidArgumentException(
                    'Scanner request headers exceed size limit.'
                );
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    private function curlResolveEntries(
        string $host,
        int $port,
        array $resolvedIps
    ): array {
        $entries = [];

        foreach ($resolvedIps as $ip) {
            $entries[] = sprintf(
                '%s:%d:%s',
                $host,
                $port,
                $ip
            );
        }

        return $entries;
    }

    private function durationMs(float $started): int
    {
        return (int) round(
            (microtime(true) - $started) * 1000
        );
    }

    private function classifyFailure(\Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (
            str_contains($message, 'timed out') ||
            str_contains($message, 'timeout')
        ) {
            return 'timeout';
        }

        if (
            str_contains($message, 'ssl') ||
            str_contains($message, 'certificate')
        ) {
            return 'tls_error';
        }

        if (
            str_contains($message, 'resolve') ||
            str_contains($message, 'dns')
        ) {
            return 'dns_error';
        }

        if (
            str_contains($message, 'connect') ||
            str_contains($message, 'connection')
        ) {
            return 'connection_error';
        }

        return 'http_connection_failed';
    }
}
