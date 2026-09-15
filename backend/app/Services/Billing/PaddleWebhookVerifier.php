<?php

namespace App\Services\Billing;

use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Http\Request;
use Paddle\SDK\Notifications\Secret;
use Paddle\SDK\Notifications\Verifier;
use RuntimeException;

class PaddleWebhookVerifier
{
    public function verify(Request $request): void
    {
        $secretValue = trim(
            (string) config('billing.paddle.webhook_secret', '')
        );

        if ($secretValue === '') {
            throw new RuntimeException(
                'Paddle webhook secret is not configured.'
            );
        }

        $signatureHeader = $request->header('Paddle-Signature');

        if (! is_string($signatureHeader)
            || trim($signatureHeader) === ''
        ) {
            throw new RuntimeException(
                'Paddle-Signature header is missing.'
            );
        }

        /*
         * Paddle SDK expects a PSR-7 RequestInterface.
         * Laravel's Illuminate\Http\Request does not provide
         * toPsrRequest(), so construct the PSR-7 request directly
         * while preserving the exact raw request body.
         */
        $psrRequest = new ServerRequest(
            $request->getMethod(),
            $request->fullUrl(),
            [
                'Paddle-Signature' => $signatureHeader,
                'Content-Type' => $request->header(
                    'Content-Type',
                    'application/json'
                ),
            ],
            $request->getContent()
        );

        $verified = (new Verifier())->verify(
            $psrRequest,
            new Secret($secretValue),
        );

        if (! $verified) {
            throw new RuntimeException(
                'Invalid Paddle webhook signature.'
            );
        }
    }
}
