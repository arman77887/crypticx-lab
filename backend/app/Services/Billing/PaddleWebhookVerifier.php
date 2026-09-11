<?php

namespace App\Services\Billing;

use Illuminate\Http\Request;
use Paddle\SDK\Notifications\Secret;
use Paddle\SDK\Notifications\Verifier;
use RuntimeException;

class PaddleWebhookVerifier
{
    public function verify(Request $request): void
    {
        $secretValue = trim(
            (string) config(
                'billing.paddle.webhook_secret',
                ''
            )
        );

        if ($secretValue === '') {
            throw new RuntimeException(
                'Paddle webhook secret is not configured.'
            );
        }

        /*
         * Paddle verification must operate on the unmodified request
         * body. Laravel's PSR-7 conversion preserves the incoming body.
         */
        $psrRequest = $request->toPsrRequest();

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
