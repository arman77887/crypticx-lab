<?php

namespace App\Services\Scanner;

final class StandaloneCurlTransport implements HttpTransport
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

        if (! extension_loaded('curl')) {
            return $this->failure(
                $started,
                'curl_unavailable',
                false
            );
        }

        $handle = curl_init();

        if ($handle === false) {
            return $this->failure(
                $started,
                'http_request_failed',
                false
            );
        }

        $headers = [];
        $bytesRead = 0;
        $responseTooLarge = false;

        $headerCallback = static function (
            $curl,
            string $line
        ) use (&$headers): int {
            $length = strlen($line);
            $trimmed = trim($line);

            if ($trimmed === '') {
                return $length;
            }

            /*
             * Ignore HTTP status lines. Header blocks are reset when a
             * new HTTP response starts, e.g. "100 Continue".
             */
            if (preg_match('/^HTTP\/\S+\s+\d+/i', $trimmed)) {
                $headers = [];

                return $length;
            }

            $position = strpos($line, ':');

            if ($position === false) {
                return $length;
            }

            $name = trim(substr($line, 0, $position));
            $value = trim(substr($line, $position + 1));

            if ($name === '') {
                return $length;
            }

            $headers[$name] ??= [];
            $headers[$name][] = $value;

            return $length;
        };

        $writeCallback = static function (
            $curl,
            string $chunk
        ) use (
            &$bytesRead,
            &$responseTooLarge,
            $maxResponseBytes
        ): int {
            $length = strlen($chunk);

            if (
                $bytesRead + $length >
                $maxResponseBytes
            ) {
                $responseTooLarge = true;

                /*
                 * Returning a short byte count aborts the transfer.
                 * The caller distinguishes this intentional abort from
                 * ordinary cURL write failures.
                 */
                return 0;
            }

            $bytesRead += $length;

            return $length;
        };

        $curlRequestHeaders = [];

        foreach ($requestHeaders as $name => $value) {
            $curlRequestHeaders[] =
                $name . ': ' . $value;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,

            /*
             * Defense-in-depth egress protocol allowlist.
             * The scanner runtime may initiate only HTTP/HTTPS transfers.
             */
            CURLOPT_PROTOCOLS_STR => 'http,https',
            CURLOPT_REDIR_PROTOCOLS_STR => 'http,https',
            CURLOPT_FOLLOWLOCATION => false,

            /*
             * Never delegate scanner egress to an inherited or ambient
             * HTTP(S) proxy. Connections must go directly to the
             * independently validated and CURLOPT_RESOLVE-pinned address.
             */
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',

            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $requestTimeoutSeconds,

            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_RESOLVE => $this->curlResolveEntries(
                $host,
                $port,
                $resolvedIps
            ),

            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => 5,

            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => true,
        ];

        if ($curlRequestHeaders !== []) {
            $options[CURLOPT_HTTPHEADER] =
                $curlRequestHeaders;
        }

        if (! curl_setopt_array($handle, $options)) {
            curl_close($handle);

            return $this->failure(
                $started,
                'http_request_failed',
                false
            );
        }

        /*
         * Install callbacks after the general option set. This prevents
         * later output-mode options from replacing PHP's body/header
         * callbacks and keeps target response bytes off stdout.
         */
        if (
            ! curl_setopt(
                $handle,
                CURLOPT_HEADERFUNCTION,
                $headerCallback
            ) ||
            ! curl_setopt(
                $handle,
                CURLOPT_WRITEFUNCTION,
                $writeCallback
            )
        ) {
            curl_close($handle);

            return $this->failure(
                $started,
                'http_request_failed',
                false
            );
        }

        $executed = curl_exec($handle);

        $errno = curl_errno($handle);
        $error = curl_error($handle);

        $status = (int) curl_getinfo(
            $handle,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($handle);

        if ($responseTooLarge) {
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

        if ($executed === false || $errno !== CURLE_OK) {
            return $this->failure(
                $started,
                $this->classifyCurlFailure(
                    $errno,
                    $error
                ),
                $this->isRetryableCurlFailure($errno)
            );
        }

        return [
            'ok' => true,
            'status' => $status,
            'successful' => $status >= 200 && $status < 300,
            'headers' => $headers,
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

    private function classifyCurlFailure(
        int $errno,
        string $error
    ): string {
        if (
            in_array(
                $errno,
                [
                    CURLE_OPERATION_TIMEDOUT,
                ],
                true
            )
        ) {
            return 'timeout';
        }

        if (
            in_array(
                $errno,
                [
                    CURLE_SSL_CONNECT_ERROR,
                    CURLE_SSL_CACERT,
                    CURLE_SSL_CACERT_BADFILE,
                    CURLE_SSL_CERTPROBLEM,
                ],
                true
            )
        ) {
            return 'tls_error';
        }

        if ($errno === CURLE_COULDNT_RESOLVE_HOST) {
            return 'dns_error';
        }

        if ($errno === CURLE_COULDNT_CONNECT) {
            return 'connection_error';
        }

        $message = strtolower($error);

        if (
            str_contains($message, 'ssl') ||
            str_contains($message, 'certificate')
        ) {
            return 'tls_error';
        }

        return 'http_connection_failed';
    }

    private function isRetryableCurlFailure(
        int $errno
    ): bool {
        return in_array(
            $errno,
            [
                CURLE_OPERATION_TIMEDOUT,
                CURLE_COULDNT_RESOLVE_HOST,
                CURLE_COULDNT_CONNECT,
                CURLE_RECV_ERROR,
                CURLE_SEND_ERROR,
            ],
            true
        );
    }

    private function failure(
        float $started,
        string $type,
        bool $retryable
    ): array {
        return [
            'ok' => false,
            'duration_ms' => $this->durationMs($started),
            'error' => [
                'type' => $type,
                'retryable' => $retryable,
            ],
        ];
    }

    private function durationMs(float $started): int
    {
        return (int) round(
            (microtime(true) - $started) * 1000
        );
    }
}
