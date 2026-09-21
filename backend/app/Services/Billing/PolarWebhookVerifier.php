<?php

namespace App\Services\Billing;

use Illuminate\Http\Request;
use RuntimeException;

class PolarWebhookVerifier
{
    private const TOLERANCE_SECONDS = 300;

    public function verify(Request $request): array
    {
        $secret = trim(
            (string) config(
                'billing.polar.webhook_secret',
                ''
            )
        );

        if ($secret === '') {
            throw new RuntimeException(
                'Polar webhook secret is not configured.'
            );
        }

        $webhookId = trim(
            (string) $request->header('webhook-id', '')
        );

        $timestamp = trim(
            (string) $request->header(
                'webhook-timestamp',
                ''
            )
        );

        $signatureHeader = trim(
            (string) $request->header(
                'webhook-signature',
                ''
            )
        );

        if (
            $webhookId === ''
            || $timestamp === ''
            || $signatureHeader === ''
        ) {
            throw new RuntimeException(
                'Missing Polar webhook signature headers.'
            );
        }

        if (! ctype_digit($timestamp)) {
            throw new RuntimeException(
                'Invalid Polar webhook timestamp.'
            );
        }

        $timestampInt = (int) $timestamp;

        if (
            abs(time() - $timestampInt)
                > self::TOLERANCE_SECONDS
        ) {
            throw new RuntimeException(
                'Polar webhook timestamp is outside tolerance.'
            );
        }

        /*
         * IMPORTANT:
         * Signature verification MUST use the exact raw request
         * body. Never JSON-decode/re-encode before verification.
         */
        $rawBody = $request->getContent();

        $signedContent =
            $webhookId
            . '.'
            . $timestamp
            . '.'
            . $rawBody;

        /*
         * Polar uses the Standard Webhooks secret format.
         * Remove the whsec_ prefix and Base64-decode the
         * remaining value before using it as the HMAC key.
         */
        if (! str_starts_with($secret, 'whsec_')) {
            throw new RuntimeException(
                'Invalid Polar webhook secret format.'
            );
        }

        $encodedKey = substr($secret, strlen('whsec_'));

        $signingKey = base64_decode($encodedKey, true);

        if ($signingKey === false || $signingKey === '') {
            throw new RuntimeException(
                'Invalid Polar webhook signing key.'
            );
        }

        $expected = base64_encode(
            hash_hmac(
                'sha256',
                $signedContent,
                $signingKey,
                true
            )
        );

        $matched = false;

        foreach (
            preg_split(
                '/\s+/',
                $signatureHeader,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) as $candidate
        ) {
            [$version, $signature] = array_pad(
                explode(',', $candidate, 2),
                2,
                ''
            );

            if (
                $version === 'v1'
                && $signature !== ''
                && hash_equals(
                    $expected,
                    $signature
                )
            ) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            throw new RuntimeException(
                'Invalid Polar webhook signature.'
            );
        }

        try {
            $payload = json_decode(
                $rawBody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'Invalid Polar webhook JSON.',
                0,
                $exception
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Invalid Polar webhook payload.'
            );
        }

        return $payload;
    }
}
