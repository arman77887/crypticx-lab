<?php

namespace App\Services\Scanner;

final class StreamTlsConnectionTransport implements TlsConnectionTransport
{
    private const CONNECT_TIMEOUT_SECONDS = 5;

    public function inspect(
        string $hostname,
        string $ip,
        int $port
    ): array {
        $isIpv6 = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        ) !== false;

        $socketHost = $isIpv6
            ? "[{$ip}]"
            : $ip;

        $isDnsHost = filter_var(
            $hostname,
            FILTER_VALIDATE_IP
        ) === false;

        $sslOptions = [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $hostname,
            'disable_compression' => true,
        ];

        if ($isDnsHost) {
            $sslOptions['SNI_enabled'] = true;
            $sslOptions['SNI_server_name'] = $hostname;
        }

        $context = stream_context_create([
            'ssl' => $sslOptions,
        ]);

        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            "tls://{$socketHost}:{$port}",
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            return [
                'connected' => false,
                'error_code' => (int) $errno,
            ];
        }

        stream_set_timeout(
            $socket,
            self::CONNECT_TIMEOUT_SECONDS
        );

        $metadata = stream_get_meta_data($socket);
        $params = stream_context_get_params($socket);

        $certificate =
            $params['options']['ssl']['peer_certificate']
            ?? null;

        $peerChain =
            $params['options']['ssl']['peer_certificate_chain']
            ?? [];

        $crypto = isset($metadata['crypto']) &&
            is_array($metadata['crypto'])
                ? $metadata['crypto']
                : [];

        $parsedCertificate = null;
        $fingerprint = null;

        if ($certificate !== null) {
            $parsed = @openssl_x509_parse($certificate);

            if (is_array($parsed)) {
                $parsedCertificate = $parsed;
            }

            try {
                $value = openssl_x509_fingerprint(
                    $certificate,
                    'sha256'
                );

                if (is_string($value)) {
                    $fingerprint = strtolower($value);
                }
            } catch (\Throwable) {
                $fingerprint = null;
            }
        }

        $parsedChain = [];

        if (is_array($peerChain)) {
            foreach (array_slice($peerChain, 0, 16) as $chainCertificate) {
                $chainParsed = @openssl_x509_parse(
                    $chainCertificate
                );

                if (is_array($chainParsed)) {
                    $parsedChain[] = $chainParsed;
                }
            }
        }

        fclose($socket);

        return [
            'connected' => true,
            'certificate_present' =>
                $certificate !== null,
            'certificate' => $parsedCertificate,
            'sha256_fingerprint' => $fingerprint,
            'certificate_chain' => $parsedChain,
            'crypto' => $crypto,
        ];
    }
}
