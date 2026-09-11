<?php

namespace App\Services\Scanner;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PublicRdapClient implements RdapClient
{
    private const MAX_RESPONSE_BYTES = 524_288;

    public function lookupDomain(
        string $hostname
    ): array {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' =>
                        'CrypticX-Lab-RDAP/1.0',
                ])
                ->withOptions([
                    'verify' => true,
                    'stream' => true,

                    /*
                     * Fixed external-service egress boundary.
                     * Do not allow an RDAP response to redirect this
                     * server-side request to another host.
                     */
                    'allow_redirects' => false,
                ])
                ->get(
                    'https://rdap.org/domain/' .
                    rawurlencode($hostname)
                );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'RDAP service request failed.',
                0,
                $exception
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'RDAP data is not available for this domain.'
            );
        }

        $stream =
            $response->toPsrResponse()->getBody();

        $body = '';
        $bytes = 0;

        while (!$stream->eof()) {
            $remaining =
                self::MAX_RESPONSE_BYTES -
                $bytes +
                1;

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

            if (
                $bytes >
                self::MAX_RESPONSE_BYTES
            ) {
                throw new RuntimeException(
                    'RDAP response exceeded the size limit.'
                );
            }

            $body .= $chunk;
        }

        try {
            $decoded = json_decode(
                $body,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'Invalid RDAP response.',
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Invalid RDAP response.'
            );
        }

        return $decoded;
    }
}
