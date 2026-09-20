<?php

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Exceptions\BillingUnavailableException;
use App\Models\User;
use App\Services\EntitlementService;
use InvalidArgumentException;

class BillingManager
{
    public function __construct(
        private EntitlementService $entitlements,
    ) {
    }

    public function gateway(): BillingGateway
    {
        $provider = strtolower(
            trim(
                (string) config(
                    'billing.provider',
                    ''
                )
            )
        );

        if ($provider === '') {
            return new NullBillingGateway();
        }

        if ($provider === 'paddle') {
            return app(
                PaddleBillingGateway::class
            );
        }

        if ($provider === 'polar') {
            return app(
                PolarBillingGateway::class
            );
        }

        throw new BillingUnavailableException(
            'The configured billing provider does not have an installed adapter.'
        );
    }

    public function status(): array
    {
        $gateway = $this->gateway();

        $checkoutEnabled = (
            (bool) config(
                'billing.checkout_enabled',
                false
            )
        );

        return [
            'configured' =>
                $checkoutEnabled
                && $gateway->configured(),

            'checkout_enabled' =>
                $checkoutEnabled
                && $gateway->configured(),

            'provider' =>
                $gateway->configured()
                    ? $gateway->providerCode()
                    : null,
        ];
    }

    public function createCheckout(
        User $user,
        string $planCode,
    ): array {
        $planCode = strtolower(
            trim($planCode)
        );

        if (
            $planCode === ''
            || ! $this->entitlements->planExists(
                $planCode
            )
        ) {
            throw new InvalidArgumentException(
                'Unknown billing plan.'
            );
        }

        if ($planCode === 'free') {
            throw new InvalidArgumentException(
                'The free plan does not require checkout.'
            );
        }

        $gateway = $this->gateway();

        if (
            ! (bool) config(
                'billing.checkout_enabled',
                false
            )
            || ! $gateway->configured()
        ) {
            throw new BillingUnavailableException(
                'Online billing is not configured yet.'
            );
        }

        return $gateway->createCheckout(
            $user,
            $planCode,
        );
    }
}
