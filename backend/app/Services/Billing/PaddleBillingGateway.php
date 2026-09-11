<?php

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Exceptions\BillingUnavailableException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaddleBillingGateway implements BillingGateway
{
    public function providerCode(): string
    {
        return 'paddle';
    }

    public function configured(): bool
    {
        $apiKey = trim(
            (string) config(
                'billing.paddle.api_key',
                ''
            )
        );

        $environment = strtolower(
            trim(
                (string) config(
                    'billing.paddle.environment',
                    'sandbox'
                )
            )
        );

        $professionalPrice = trim(
            (string) config(
                'billing.paddle.prices.professional',
                ''
            )
        );

        $teamPrice = trim(
            (string) config(
                'billing.paddle.prices.team',
                ''
            )
        );

        return (
            $apiKey !== ''
            && in_array(
                $environment,
                ['sandbox', 'production'],
                true
            )
            && str_starts_with(
                $professionalPrice,
                'pri_'
            )
            && str_starts_with(
                $teamPrice,
                'pri_'
            )
        );
    }

    public function createCheckout(
        User $user,
        string $planCode,
    ): array {
        if (! $this->configured()) {
            throw new BillingUnavailableException(
                'Paddle billing is not fully configured.'
            );
        }

        $priceId = trim(
            (string) config(
                "billing.paddle.prices.{$planCode}",
                ''
            )
        );

        if (! str_starts_with(
            $priceId,
            'pri_'
        )) {
            throw new BillingUnavailableException(
                'The selected Paddle price is not configured.'
            );
        }

        $apiKey = trim(
            (string) config(
                'billing.paddle.api_key',
                ''
            )
        );

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->timeout(20)
                ->post(
                    $this->apiBaseUrl()
                        . '/transactions',
                    [
                        'items' => [
                            [
                                'price_id' =>
                                    $priceId,
                                'quantity' => 1,
                            ],
                        ],

                        'collection_mode' =>
                            'automatic',

                        /*
                         * This metadata originates on the authenticated
                         * backend. Do not accept user_id or plan_code
                         * from Paddle.js/browser input.
                         *
                         * Paddle copies transaction custom_data to the
                         * resulting recurring subscription.
                         */
                        'custom_data' => [
                            'user_id' =>
                                (string) $user->id,
                            'plan_code' =>
                                $planCode,
                        ],
                    ],
                );
        } catch (ConnectionException $exception) {
            throw new BillingUnavailableException(
                'Unable to reach Paddle billing.'
            );
        } catch (Throwable $exception) {
            throw new BillingUnavailableException(
                'Paddle checkout could not be created.'
            );
        }

        if (! $response->successful()) {
            throw new BillingUnavailableException(
                'Paddle rejected the checkout transaction.'
            );
        }

        $transactionId = trim(
            (string) $response->json(
                'data.id',
                ''
            )
        );

        if (! str_starts_with(
            $transactionId,
            'txn_'
        )) {
            throw new BillingUnavailableException(
                'Paddle returned an invalid transaction.'
            );
        }

        return [
            'transaction_id' =>
                $transactionId,
            'provider' => 'paddle',
        ];
    }

    private function apiBaseUrl(): string
    {
        $environment = strtolower(
            trim(
                (string) config(
                    'billing.paddle.environment',
                    'sandbox'
                )
            )
        );

        return $environment === 'sandbox'
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';
    }
}
